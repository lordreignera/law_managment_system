<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\LegalLetter;
use App\Models\Matter;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageBroadcaster;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $client = $this->client($request);
        $matters = $this->mattersFor($client)->with(['practiceArea', 'assignments.user'])->latest()->take(6)->get();
        $matterIds = $this->mattersFor($client)->pluck('id');

        return view('client-portal.dashboard', [
            'client' => $client,
            'matters' => $matters,
            'summary' => [
                'active_matters' => $this->mattersFor($client)->whereNotIn('status', ['closed', 'archived'])->count(),
                'shared_documents' => Attachment::where('attachable_type', Matter::class)
                    ->whereIn('attachable_id', $matterIds)
                    ->where('is_client_visible', true)
                    ->count(),
                'unread_messages' => Conversation::unreadForUser($request->user())->count(),
                'assigned_advocates' => $matters->flatMap->assignments->pluck('user_id')->filter()->unique()->count(),
            ],
            'conversations' => Conversation::forUser($request->user())
                ->with(['matter', 'latestMessage.sender'])
                ->where('audience_type', 'client_matter')
                ->latest('last_message_at')
                ->take(5)
                ->get(),
        ]);
    }

    public function matters(Request $request)
    {
        $client = $this->client($request);

        return view('client-portal.matters.index', [
            'client' => $client,
            'matters' => $this->mattersFor($client)
                ->with(['practiceArea', 'assignments.user'])
                ->when($request->filled('search'), function (Builder $query) use ($request) {
                    $search = $request->string('search')->toString();

                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('reference_no', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function messages(Request $request, ?Conversation $conversation = null)
    {
        $client = $this->client($request);
        $user = $request->user();

        $query = Conversation::forUser($user)
            ->where('audience_type', 'client_matter')
            ->where('client_id', $client->id)
            ->with(['matter.practiceArea', 'latestMessage.sender', 'participants']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhereHas('matter', function (Builder $query) use ($search) {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('reference_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('latestMessage', fn (Builder $query) => $query->where('body', 'like', "%{$search}%"));
            });
        }

        $conversations = $query
            ->latest('last_message_at')
            ->paginate(10)
            ->withQueryString();

        $selectedConversation = $conversation ?: $conversations->first();

        if ($selectedConversation) {
            abort_unless(
                (int) $selectedConversation->client_id === (int) $client->id
                && $selectedConversation->audience_type === 'client_matter'
                && $selectedConversation->participants()->where('user_id', $user->id)->exists(),
                403
            );

            $selectedConversation->load([
                'matter.practiceArea',
                'matter.assignments.user',
                'messages.sender',
                'participants.user',
            ]);
            $selectedConversation->participants()
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);
        }

        return view('client-portal.messages.index', [
            'client' => $client,
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'filters' => $request->only('search'),
        ]);
    }

    public function showMatter(Request $request, Matter $matter)
    {
        $client = $this->client($request);
        $this->authorizeMatter($client, $matter);

        $conversation = $this->matterConversation($request->user(), $client, $matter);
        $conversation->load(['participants.user', 'messages.sender']);

        return view('client-portal.matters.show', [
            'client' => $client,
            'matter' => $matter->load(['practiceArea', 'assignments.user', 'courtEvents']),
            'documents' => Attachment::where('attachable_type', Matter::class)
                ->where('attachable_id', $matter->id)
                ->where('is_client_visible', true)
                ->latest()
                ->get(),
            'letters' => LegalLetter::where('matter_id', $matter->id)
                ->where('client_visible', true)
                ->whereIn('status', ['sent', 'received', 'closed'])
                ->latest('letter_date')
                ->get(),
            'conversation' => $conversation,
        ]);
    }

    public function sendMatterMessage(Request $request, Matter $matter)
    {
        $client = $this->client($request);
        $this->authorizeMatter($client, $matter);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = $this->matterConversation($request->user(), $client, $matter);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $conversation->participants()->where('user_id', $request->user()->id)->update(['last_read_at' => now()]);
        MessageBroadcaster::dispatch($message);

        return redirect()
            ->route('client.matters.show', $matter)
            ->with('status', 'Message sent to your advocate.');
    }

    public function downloadDocument(Request $request, Attachment $attachment)
    {
        $client = $this->client($request);

        abort_unless($attachment->is_client_visible && $attachment->attachable_type === Matter::class, 403);

        $matter = Matter::whereKey($attachment->attachable_id)->firstOrFail();
        $this->authorizeMatter($client, $matter);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function downloadLetter(Request $request, LegalLetter $letter)
    {
        $client = $this->client($request);

        abort_unless($letter->client_visible && in_array($letter->status, ['sent', 'received', 'closed'], true), 403);

        if ($letter->matter_id) {
            $this->authorizeMatter($client, $letter->matter);
        } else {
            abort_unless((int) $letter->client_id === (int) $client->id, 403);
        }

        return Pdf::loadView('modules.letters.pdf.letter', [
            'letter' => $letter->load(['client', 'matter.client', 'recoveryAccount', 'creator', 'signer']),
            'company' => CompanySetting::current(),
        ])->download(str($letter->reference_no)->replace('/', '-')->lower().'.pdf');
    }

    private function client(Request $request): Client
    {
        $account = $request->user()->clientPortalAccount;

        if ($request->user()->hasVerifiedEmail() && ! $account->verified_at) {
            $account->update(['verified_at' => now()]);
        }

        return $account->client;
    }

    private function mattersFor(Client $client): Builder
    {
        return Matter::query()->where('client_id', $client->id);
    }

    private function authorizeMatter(Client $client, Matter $matter): void
    {
        abort_unless((int) $matter->client_id === (int) $client->id, 403);
    }

    private function matterConversation(User $portalUser, Client $client, Matter $matter): Conversation
    {
        return DB::transaction(function () use ($portalUser, $client, $matter) {
            $conversation = Conversation::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'matter_id' => $matter->id,
                    'audience_type' => 'client_matter',
                ],
                [
                    'created_by' => $portalUser->id,
                    'title' => 'Client Portal: '.$matter->reference_no,
                    'allow_replies' => true,
                    'last_message_at' => now(),
                ]
            );

            $participantIds = collect([$portalUser->id])
                ->merge($matter->assignments()->pluck('user_id'))
                ->push($client->client_in_charge_id)
                ->filter()
                ->unique()
                ->values();

            $participantIds->each(function (int $userId) use ($conversation, $portalUser) {
                $conversation->participants()->firstOrCreate(
                    ['user_id' => $userId],
                    ['last_read_at' => $userId === $portalUser->id ? now() : null]
                );
            });

            return $conversation;
        });
    }
}
