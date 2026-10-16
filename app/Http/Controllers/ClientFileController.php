<?php

namespace App\Http\Controllers;

use App\Models\AdrResolution;
use App\Models\AuditLog;
use App\Models\BillingType;
use App\Models\Client;
use App\Models\File;
use App\Models\Matter;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientFileController extends Controller
{
    public function create(Client $client, Request $request)
    {
        $adr = null;

        if ($request->filled('adr')) {
            $adr = AdrResolution::where('client_id', $client->id)->find($request->integer('adr'));
        }

        return view('modules.files.create', [
            'client' => $client,
            'adr' => $adr,
            'fileNumber' => MonthlyReferenceNumber::make(File::class, 'file_number', 'FL'),
            'billingTypes' => BillingType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'paymentSources' => File::RETAINER_PAYMENT_SOURCES,
            'matters' => Matter::visibleTo($request->user())->where('client_id', $client->id)->latest()->get(['id', 'reference_no', 'title']),
        ]);
    }

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:191'],
            'matter_id' => ['nullable', 'integer', 'exists:matters,id'],
            'adr_resolution_id' => ['nullable', 'integer', Rule::exists('adr_resolutions', 'id')->where('client_id', $client->id)],
            'billing_type_id' => ['required', 'exists:billing_types,id'],
            'agreed_fee_amount' => ['required', 'numeric', 'min:0'],
            'engagement_letter_sent_on' => ['nullable', 'date'],
            'engagement_letter' => ['required_with:engagement_letter_sent_on', 'nullable', 'file', 'max:5120'],
            'fee_agreement_sent_on' => ['nullable', 'date'],
            'fee_agreement' => ['required_with:fee_agreement_sent_on', 'nullable', 'file', 'max:5120'],
            'client_accepted_on' => ['nullable', 'date'],
            'retainer_required' => ['nullable', 'boolean'],
            'retainer_amount' => ['nullable', 'numeric', 'min:0', 'required_if:retainer_required,1'],
            'retainer_payment_source' => ['nullable', 'required_if:retainer_required,1', Rule::in(array_keys(File::RETAINER_PAYMENT_SOURCES))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if (! empty($data['matter_id']) && ! Matter::visibleTo($request->user())
            ->where('client_id', $client->id)
            ->whereKey($data['matter_id'])
            ->exists()) {
            return back()->withErrors(['matter_id' => 'Select a matter belonging to this client.'])->withInput();
        }

        $file = DB::transaction(function () use ($data, $request, $client) {
            $file = File::create([
                'client_id' => $client->id,
                'adr_resolution_id' => $data['adr_resolution_id'] ?? null,
                'matter_id' => $data['matter_id'] ?? null,
                'billing_type_id' => $data['billing_type_id'],
                'created_by' => auth()->id(),
                'file_number' => MonthlyReferenceNumber::make(File::class, 'file_number', 'FL'),
                'file_name' => $data['file_name'],
                'agreed_fee_amount' => $data['agreed_fee_amount'],
                'engagement_letter_sent_on' => $data['engagement_letter_sent_on'] ?? null,
                'fee_agreement_sent_on' => $data['fee_agreement_sent_on'] ?? null,
                'client_accepted_on' => $data['client_accepted_on'] ?? null,
                'retainer_required' => $request->boolean('retainer_required'),
                'retainer_amount' => $request->boolean('retainer_required') ? ($data['retainer_amount'] ?? 0) : null,
                'retainer_payment_source' => $request->boolean('retainer_required') ? ($data['retainer_payment_source'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($request->hasFile('engagement_letter')) {
                $file->addAttachment($request->file('engagement_letter'), ['category' => 'engagement-letter']);
            }

            if ($request->hasFile('fee_agreement')) {
                $file->addAttachment($request->file('fee_agreement'), ['category' => 'fee-agreement']);
            }

            return $file;
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'File '.$file->file_number.' opened.');
    }

    public function show(File $file)
    {
        return view('modules.files.show', [
            'file' => $file->load(['client', 'billingType', 'adrResolution', 'matter', 'attachments.uploader']),
        ]);
    }

    public function storeDocument(Request $request, File $file)
    {
        $data = $request->validate([
            'document' => ['required', 'file', 'max:10240'],
            'category' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:191'],
            'document_key' => ['nullable', 'string', 'max:191'],
            'replace_document_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'is_client_visible' => ['nullable', 'boolean'],
        ]);

        $replacement = null;
        if (! empty($data['replace_document_id'])) {
            $replacement = $file->attachments()->whereKey($data['replace_document_id'])->first();
            abort_unless($replacement, 422, 'Select a document belonging to this file.');
        }

        $documentKey = $data['document_key']
            ?? $replacement?->document_key
            ?? Str::slug(pathinfo($request->file('document')->getClientOriginalName(), PATHINFO_FILENAME));
        $version = ((int) $file->attachments()->where('document_key', $documentKey)->max('version_number')) + 1;
        $previousVersions = $file->attachments()
            ->where('document_key', $documentKey)
            ->where('is_current', true)
            ->get();
        $previousVersions->each(fn ($attachment) => $attachment->update(['is_current' => false]));

        $attachment = $file->addAttachment($request->file('document'), [
            'category' => $data['category'] ?: 'file-document',
            'title' => $data['title'] ?? $request->file('document')->getClientOriginalName(),
            'document_key' => $documentKey,
            'version_number' => $version,
            'is_current' => true,
            'is_client_visible' => $request->boolean('is_client_visible'),
        ]);
        AuditLog::recordFor($attachment, 'uploaded', [], ['version_number' => $version, 'document_key' => $documentKey]);
        $previousVersions->each(fn ($previous) => AuditLog::recordFor($previous, 'superseded', ['is_current' => true], ['is_current' => false, 'replaced_by' => $attachment->id]));

        return redirect()
            ->route('clients.files.show', $file)
            ->with('status', 'Document uploaded.');
    }
}
