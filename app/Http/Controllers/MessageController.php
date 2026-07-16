<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageBroadcaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderInbox($request);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $this->markReadFor($conversation, $request->user());

        return $this->renderInbox($request, $conversation);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'audience_type' => ['required', Rule::in(array_keys($this->composableAudienceTypes()))],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:5000'],
            'recipient_user_ids' => ['required_if:audience_type,users', 'array'],
            'recipient_user_ids.*' => ['exists:users,id'],
            'department_id' => ['required_if:audience_type,department', 'nullable', 'exists:departments,id'],
            'branch_id' => ['required_if:audience_type,branch', 'nullable', 'exists:branches,id'],
            'allow_replies' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => $this->messageAttachmentRules(),
        ]);

        if ($data['audience_type'] === 'firm') {
            abort_unless($request->user()->can('messages.broadcast'), 403);
        }

        $participantIds = $this->participantIdsFor($request->user(), $data);

        if ($participantIds->isEmpty()) {
            throw ValidationException::withMessages([
                'audience_type' => 'No active recipients were found for this message.',
            ]);
        }

        [$conversation, $message] = DB::transaction(function () use ($request, $data, $participantIds) {
            $conversation = Conversation::create([
                'created_by' => $request->user()->id,
                'branch_id' => $data['audience_type'] === 'branch' ? $data['branch_id'] : null,
                'department_id' => $data['audience_type'] === 'department' ? $data['department_id'] : null,
                'audience_type' => $data['audience_type'],
                'title' => $data['title'],
                'is_broadcast' => $data['audience_type'] === 'firm',
                'allow_replies' => $request->boolean('allow_replies', true),
                'last_message_at' => now(),
            ]);

            $participantIds->each(function (int $userId) use ($conversation, $request) {
                $conversation->participants()->create([
                    'user_id' => $userId,
                    'last_read_at' => $userId === $request->user()->id ? now() : null,
                ]);
            });

            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $data['body'] ?? '',
                'sent_at' => now(),
            ]);

            $this->storeAttachments($request, $message);

            return [$conversation, $message];
        });

        MessageBroadcaster::dispatch($message);

        return redirect()
            ->route('messages.show', $conversation)
            ->with('status', 'Message sent.');
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        abort_unless($conversation->allow_replies, 422, 'Replies are disabled for this conversation.');

        $data = $request->validate([
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => $this->messageAttachmentRules(),
        ]);

        $message = DB::transaction(function () use ($request, $conversation, $data) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $request->user()->id,
                'body' => $data['body'] ?? '',
                'sent_at' => now(),
            ]);

            $this->storeAttachments($request, $message);

            $conversation->update(['last_message_at' => now()]);
            $this->markReadFor($conversation, $request->user());

            return $message;
        });

        MessageBroadcaster::dispatch($message);

        return redirect()
            ->route('messages.show', $conversation)
            ->with('status', 'Reply sent.');
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $this->markReadFor($conversation, $request->user());

        return back()->with('status', 'Conversation marked as read.');
    }

    private function renderInbox(Request $request, ?Conversation $selectedConversation = null)
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forUser($user)
            ->with(['creator', 'latestMessage.sender', 'latestMessage.attachments', 'participants.user'])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhereHas('messages', fn (Builder $query) => $query->where('body', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('audience_type'), fn (Builder $query) => $query->where('audience_type', $request->string('audience_type')->toString()))
            ->when($request->boolean('unread'), fn (Builder $query) => $query->unreadForUser($user))
            ->latest('last_message_at')
            ->paginate(12)
            ->withQueryString();

        $selectedConversation ??= $conversations->first();

        if ($selectedConversation) {
            $selectedConversation->load([
                'creator',
                'branch',
                'department',
                'participants.user',
                'messages.sender',
                'messages.attachments',
            ]);
        }

        return view('modules.messages.index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'users' => $this->activeUsers()
                ->whereKeyNot($user->id)
                ->with(['branch', 'department', 'roles'])
                ->get(['id', 'name', 'email', 'branch_id', 'department_id']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'branch_id']),
            'audienceTypes' => $this->composableAudienceTypes(),
            'filters' => $request->only(['search', 'audience_type', 'unread']),
        ]);
    }

    private function participantIdsFor(User $sender, array $data)
    {
        $participants = collect([$sender->id]);

        $recipients = match ($data['audience_type']) {
            'self' => collect(),
            'users' => $this->activeUsers()->whereIn('id', $data['recipient_user_ids'] ?? [])->pluck('id'),
            'department' => $this->activeUsers()->where('department_id', $data['department_id'] ?? null)->pluck('id'),
            'branch' => $this->activeUsers()->where('branch_id', $data['branch_id'] ?? null)->pluck('id'),
            'firm' => $this->activeUsers()->pluck('id'),
            default => collect(),
        };

        return $participants->merge($recipients)->unique()->values();
    }

    private function activeUsers()
    {
        return User::query()
            ->whereHas('staffProfile', fn (Builder $query) => $query->where('employment_status', 'active'))
            ->orderBy('name');
    }

    private function composableAudienceTypes(): array
    {
        return collect(Conversation::AUDIENCE_TYPES)
            ->except('client_matter')
            ->all();
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->where('user_id', $request->user()->id)->exists(),
            403
        );
    }

    private function markReadFor(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    private function storeAttachments(Request $request, Message $message): void
    {
        foreach ($request->file('attachments', []) as $attachment) {
            $message->addAttachment($attachment, [
                'category' => $this->attachmentCategory($attachment->getMimeType()),
                'title' => $attachment->getClientOriginalName(),
            ]);
        }
    }

    private function attachmentCategory(?string $mimeType): string
    {
        return match (true) {
            str_starts_with((string) $mimeType, 'image/') => 'chat_image',
            str_starts_with((string) $mimeType, 'video/') => 'chat_video',
            str_starts_with((string) $mimeType, 'audio/') => 'chat_audio',
            default => 'chat_document',
        };
    }

    private function messageAttachmentRules(): array
    {
        return [
            'file',
            'max:51200',
            'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/webm,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/ogg,audio/webm,application/ogg',
        ];
    }
}
