<?php

namespace App\Http\Controllers;

use App\Models\AdrResolution;
use App\Models\BillingType;
use App\Models\Client;
use App\Models\File;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:191'],
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

        $file = DB::transaction(function () use ($data, $request, $client) {
            $file = File::create([
                'client_id' => $client->id,
                'adr_resolution_id' => $data['adr_resolution_id'] ?? null,
                'matter_id' => $client->matter?->id,
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
        ]);

        $file->addAttachment($request->file('document'), [
            'category' => $data['category'] ?: 'file-document',
        ]);

        return redirect()
            ->route('clients.files.show', $file)
            ->with('status', 'Document uploaded.');
    }
}
