@extends('layouts.admin')

@section('title', 'Open File')
@section('page-title', 'Open File')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Open File</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('clients.files.store', $client) }}" enctype="multipart/form-data">
            @csrf
            @if ($adr)
                <input type="hidden" name="adr_resolution_id" value="{{ $adr->id }}">
            @endif

            <div class="kfms-form-grid">
                <label>
                    <span>File Number</span>
                    <input type="text" value="{{ $fileNumber }}" readonly disabled>
                </label>

                @if ($adr)
                    <label>
                        <span>From ADR</span>
                        <input type="text" value="{{ $adr->adr_no }}" readonly disabled>
                    </label>
                @endif

                <label>
                    <span>File Name</span>
                    <input type="text" name="file_name" value="{{ old('file_name', $adr?->title) }}" required>
                    @error('file_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Billing Type</span>
                    <select name="billing_type_id" required>
                        <option value="">Select billing type</option>
                        @foreach ($billingTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('billing_type_id') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('billing_type_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Agreed Fee Amount</span>
                    <input type="number" step="0.01" min="0" name="agreed_fee_amount" value="{{ old('agreed_fee_amount') }}" required>
                    @error('agreed_fee_amount') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Engagement Letter Sent</span>
                    <input type="date" name="engagement_letter_sent_on" value="{{ old('engagement_letter_sent_on') }}">
                    @error('engagement_letter_sent_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Engagement Letter (upload)</span>
                    <input type="file" name="engagement_letter">
                    @error('engagement_letter') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Fee Agreement Sent</span>
                    <input type="date" name="fee_agreement_sent_on" value="{{ old('fee_agreement_sent_on') }}">
                    @error('fee_agreement_sent_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Fee Agreement (upload)</span>
                    <input type="file" name="fee_agreement">
                    @error('fee_agreement') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client Accepted On</span>
                    <input type="date" name="client_accepted_on" value="{{ old('client_accepted_on') }}">
                    @error('client_accepted_on') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-check-row">
                    <input type="checkbox" name="retainer_required" value="1" @checked(old('retainer_required'))>
                    <span>Retainer Required</span>
                </label>

                <label>
                    <span>Retainer Amount</span>
                    <input type="number" step="0.01" min="0" name="retainer_amount" value="{{ old('retainer_amount') }}">
                    @error('retainer_amount') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Retainer Payment Mode</span>
                    <select name="retainer_payment_source">
                        <option value="">Select payment mode</option>
                        @foreach ($paymentSources as $value => $label)
                            <option value="{{ $value }}" @selected(old('retainer_payment_source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('retainer_payment_source') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>File Notes</span>
                    <textarea name="notes" rows="5">{{ old('notes') }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">Cancel</a>
                <button type="submit">Open File</button>
            </div>
        </form>
    </section>
@endsection
