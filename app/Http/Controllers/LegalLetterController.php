<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\LegalLetter;
use App\Models\LetterTemplate;
use App\Models\Matter;
use App\Models\RecoveryAccount;
use App\Models\User;
use App\Support\StorageUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LegalLetterController extends Controller
{
    public function dashboard()
    {
        $letters = LegalLetter::query()->with(['client', 'matter', 'creator'])->latest()->get();

        return view('modules.letters.dashboard', [
            'summary' => [
                'Drafts' => $letters->where('status', 'draft')->count(),
                'Pending Review' => $letters->where('status', 'pending_review')->count(),
                'Sent' => $letters->where('status', 'sent')->count(),
                'Received Copies' => $letters->where('status', 'received')->count(),
            ],
            'recentLetters' => $letters->take(8),
            'pendingLetters' => $letters->where('status', 'pending_review')->take(8),
            'sentLetters' => $letters->whereIn('status', ['sent', 'received'])->take(8),
        ]);
    }

    public function index(Request $request)
    {
        $letters = $this->filteredQuery($request)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.letters.index', [
            'letters' => $letters,
            'filters' => $request->only(['search', 'status', 'letter_type']),
            'statuses' => LegalLetter::STATUSES,
            'types' => LetterTemplate::CATEGORIES,
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->string('letter_type')->toString() ?: 'general';
        $template = $request->integer('template_id')
            ? LetterTemplate::find($request->integer('template_id'))
            : LetterTemplate::where('category', $type)->where('is_active', true)->orderBy('sort_order')->first();

        return view('modules.letters.create', $this->formData([
            'letter' => new LegalLetter([
                'letter_template_id' => $template?->id,
                'letterhead_id' => $template?->letterhead_id,
                'letter_type' => $template?->category ?: $type,
                'reference_no' => LegalLetter::nextReference($template?->category ?: $type),
                'letter_date' => now()->toDateString(),
                'client_id' => $request->integer('client_id') ?: null,
                'matter_id' => $request->integer('matter_id') ?: null,
                'recovery_account_id' => $request->integer('recovery_account_id') ?: null,
                'recipient_name' => '',
                'subject' => $template?->subject ?: '',
                'body' => $template?->body ?: '',
                'status' => 'draft',
                'signature_mode' => auth()->user()?->signature_path ? 'profile' : 'none',
            ]),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['reference_no'] = ($data['reference_no'] ?? null) ?: LegalLetter::nextReference($data['letter_type']);
        $data['created_by'] = $request->user()->id;
        $data['branch_id'] = $request->user()->branch_id;
        $data['department_id'] = $request->user()->department_id;
        $data = $this->prepareSignature($request, $data);

        $letter = LegalLetter::create($data);
        $this->storeAttachments($request, $letter);

        return redirect()
            ->route('letters.show', $letter)
            ->with('status', 'Letter drafted.');
    }

    public function show(LegalLetter $letter)
    {
        return view('modules.letters.show', [
            'letter' => $letter->load([
                'template',
                'client',
                'matter.client',
                'recoveryAccount.client',
                'creator',
                'signer',
                'approver',
                'sender',
                'shares.user',
                'attachments.uploader',
            ]),
            'staff' => $this->staffUsers(),
        ]);
    }

    public function edit(LegalLetter $letter)
    {
        abort_if(in_array($letter->status, ['sent', 'received', 'closed'], true), 403);

        return view('modules.letters.edit', $this->formData([
            'letter' => $letter,
        ]));
    }

    public function update(Request $request, LegalLetter $letter)
    {
        abort_if(in_array($letter->status, ['sent', 'received', 'closed'], true), 403);

        $data = $this->validatedData($request, $letter);
        $data = $this->prepareSignature($request, $data, $letter);

        $letter->update($data);
        $this->storeAttachments($request, $letter);

        return redirect()
            ->route('letters.show', $letter)
            ->with('status', 'Letter updated.');
    }

    public function submit(LegalLetter $letter)
    {
        $letter->update([
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Letter submitted for review.');
    }

    public function approve(Request $request, LegalLetter $letter)
    {
        $data = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $letter->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $data['approval_notes'] ?? null,
        ]);

        return back()->with('status', 'Letter approved.');
    }

    public function markSent(Request $request, LegalLetter $letter)
    {
        $data = $request->validate([
            'sent_at' => ['nullable', 'date'],
            'sent_notes' => ['nullable', 'string', 'max:3000'],
            'client_visible' => ['nullable', 'boolean'],
        ]);

        $letter->update([
            'status' => 'sent',
            'sent_by' => $request->user()->id,
            'sent_at' => $data['sent_at'] ?? now(),
            'sent_notes' => $data['sent_notes'] ?? null,
            'client_visible' => $request->boolean('client_visible'),
        ]);

        return back()->with('status', 'Letter marked as sent.');
    }

    public function uploadReceivedCopy(Request $request, LegalLetter $letter)
    {
        $data = $request->validate([
            'received_at' => ['required', 'date'],
            'received_notes' => ['nullable', 'string', 'max:3000'],
            'received_copy' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $letter->update([
            'status' => 'received',
            'received_at' => $data['received_at'],
            'received_notes' => $data['received_notes'] ?? null,
        ]);

        $letter->addAttachment($request->file('received_copy'), [
            'category' => 'received_copy',
            'title' => 'Received copy',
            'is_client_visible' => false,
        ]);

        return back()->with('status', 'Received copy uploaded.');
    }

    public function share(Request $request, LegalLetter $letter)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['user_ids'] as $userId) {
            $letter->shares()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'shared_by' => $request->user()->id,
                    'message' => $data['message'] ?? null,
                ]
            );
        }

        return back()->with('status', 'Letter shared internally.');
    }

    public function toggleClientVisibility(Request $request, LegalLetter $letter)
    {
        abort_unless($letter->client_id || $letter->matter_id, 422);

        $letter->update([
            'client_visible' => $request->boolean('client_visible'),
        ]);

        return back()->with('status', $letter->client_visible ? 'Letter is visible in the client portal.' : 'Letter hidden from client portal.');
    }

    public function pdf(LegalLetter $letter)
    {
        $letter->load(['client', 'matter.client', 'recoveryAccount', 'creator', 'signer']);

        return Pdf::loadView('modules.letters.pdf.letter', [
            'letter' => $letter,
            'company' => CompanySetting::current(),
        ])->download(str($letter->reference_no)->replace('/', '-')->lower().'.pdf');
    }

    public function destroy(LegalLetter $letter)
    {
        abort_if(in_array($letter->status, ['sent', 'received', 'closed'], true), 403);

        $letter->delete();

        return redirect()
            ->route('letters.index')
            ->with('status', 'Letter deleted.');
    }

    private function filteredQuery(Request $request): Builder
    {
        return LegalLetter::query()
            ->with(['client', 'matter', 'creator'])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('matter', fn (Builder $query) => $query->where('reference_no', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('letter_type'), fn (Builder $query) => $query->where('letter_type', $request->string('letter_type')->toString()));
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'templates' => LetterTemplate::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'clients' => Client::where('status', 'active')->orderBy('name')->limit(300)->get(['id', 'client_no', 'name', 'organization_name', 'first_name', 'last_name', 'email', 'phone']),
            'matters' => Matter::with('client')->latest()->limit(300)->get(['id', 'client_id', 'reference_no', 'title']),
            'recoveries' => RecoveryAccount::with('client')->latest()->limit(300)->get(['id', 'recovery_client_id', 'account_number', 'debtor_name']),
            'types' => LetterTemplate::CATEGORIES,
            'signatureModes' => LegalLetter::SIGNATURE_MODES,
        ], $overrides);
    }

    private function validatedData(Request $request, ?LegalLetter $letter = null): array
    {
        return $request->validate([
            'reference_no' => ['nullable', 'string', 'max:80', Rule::unique('legal_letters', 'reference_no')->ignore($letter?->id)],
            'letter_template_id' => ['nullable', 'exists:letter_templates,id'],
            'letterhead_id' => ['nullable', 'exists:letterheads,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'recovery_account_id' => ['nullable', 'exists:recovery_accounts,id'],
            'letter_type' => ['required', Rule::in(array_keys(LetterTemplate::CATEGORIES))],
            'recipient_name' => ['required', 'string', 'max:191'],
            'recipient_contact' => ['nullable', 'string', 'max:191'],
            'recipient_email' => ['nullable', 'email', 'max:191'],
            'recipient_address' => ['nullable', 'string', 'max:2000'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'letter_date' => ['required', 'date'],
            'signature_mode' => ['required', Rule::in(array_keys(LegalLetter::SIGNATURE_MODES))],
            'signature_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'drawn_signature' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
    }

    private function prepareSignature(Request $request, array $data, ?LegalLetter $letter = null): array
    {
        $data['signed_by'] = null;
        $data['signature_path'] = $letter?->signature_path;

        if ($data['signature_mode'] === 'none') {
            $data['signature_path'] = null;
        }

        if ($data['signature_mode'] === 'profile') {
            $data['signature_path'] = $request->user()->signature_path;
            $data['signed_by'] = $request->user()->id;
        }

        if ($data['signature_mode'] === 'upload' && $request->hasFile('signature_upload')) {
            $data['signature_path'] = $request->file('signature_upload')->store('signatures/letters', StorageUrl::profileDisk());
            $data['signed_by'] = $request->user()->id;
        }

        if ($data['signature_mode'] === 'drawn' && str_starts_with((string) $request->input('drawn_signature'), 'data:image/png;base64,')) {
            $base64 = Str::after($request->input('drawn_signature'), 'data:image/png;base64,');
            $path = 'signatures/letters/'.Str::uuid().'.png';
            Storage::disk(StorageUrl::profileDisk())->put($path, base64_decode($base64));
            $data['signature_path'] = $path;
            $data['signed_by'] = $request->user()->id;
        }

        unset($data['signature_upload'], $data['drawn_signature'], $data['documents']);

        return $data;
    }

    private function storeAttachments(Request $request, LegalLetter $letter): void
    {
        foreach ($request->file('documents', []) as $document) {
            $letter->addAttachment($document, [
                'category' => 'supporting_document',
                'title' => 'Supporting document',
                'is_client_visible' => false,
            ]);
        }
    }

    private function staffUsers()
    {
        return User::query()
            ->where(function ($query) {
                $query->whereNull('account_type')->orWhere('account_type', 'staff');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
