@extends('layouts.admin')

@section('title', 'File')
@section('page-title', 'File')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $file->file_number }}</h2>
                <span>{{ $file->file_name }} - {{ $file->client?->display_name }}</span>
            </div>
            <div class="kfms-header-actions">
                <a class="kfms-link-btn" href="{{ route('clients.show', $file->client) }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Client
                </a>
                @if ($file->matter)
                    <a class="kfms-btn" href="{{ route('matters.show', $file->matter) }}">
                        <i class="mdi mdi-briefcase-eye"></i>
                        View Matter
                    </a>
                @elseif (! $file->client?->matter)
                    <a class="kfms-btn" href="{{ route('clients.matters.create', $file->client) }}">
                        <i class="mdi mdi-briefcase-plus"></i>
                        Open Matter
                    </a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div><span>File Number</span><strong>{{ $file->file_number }}</strong></div>
            <div><span>File Name</span><strong>{{ $file->file_name }}</strong></div>
            <div><span>Billing Type</span><strong>{{ $file->billingType?->name ?: '-' }}</strong></div>
            <div><span>Agreed Fee</span><strong>{{ $file->agreed_fee_amount ? number_format($file->agreed_fee_amount, 2) : '-' }}</strong></div>
            <div><span>Retainer</span><strong>{{ $file->retainer_required ? number_format($file->retainer_amount, 2).' via '.$file->retainerPaymentSourceLabel() : '-' }}</strong></div>
            <div><span>Engagement Letter Sent</span><strong>{{ $file->engagement_letter_sent_on?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Fee Agreement Sent</span><strong>{{ $file->fee_agreement_sent_on?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Client Accepted On</span><strong>{{ $file->client_accepted_on?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>From ADR</span><strong>{{ $file->adrResolution?->adr_no ?: '-' }}</strong></div>
            <div><span>Matter</span><strong>{{ $file->matter?->reference_no ?: 'Not opened' }}</strong></div>
        </div>

        @if ($file->notes)
            <div class="kfms-section-heading"><h3>Notes</h3></div>
            <p class="kfms-muted-text">{{ $file->notes }}</p>
        @endif
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Documents</h2>
                <span>{{ $file->attachments->count() }} uploaded files stored in the document bucket</span>
            </div>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Category</th>
                        <th>Uploaded By</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($file->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->original_name }}</td>
                            <td>{{ str($attachment->category ?: 'file-document')->headline() }}</td>
                            <td>{{ $attachment->uploader?->name ?: '-' }}</td>
                            <td>{{ number_format(($attachment->size ?? 0) / 1024, 1) }} KB</td>
                            <td>{{ $attachment->created_at?->format('d M Y, H:i') }}</td>
                            <td><a class="kfms-link-btn" href="{{ route('attachments.download', $attachment) }}"><i class="mdi mdi-download"></i> Download</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="kfms-empty">No documents uploaded for this file yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="kfms-section-heading"><h3>Upload Document</h3></div>
        <form class="kfms-form" method="POST" action="{{ route('clients.files.documents.store', $file) }}" enctype="multipart/form-data">
            @csrf
            <div class="kfms-form-grid">
                <label>
                    <span>Document</span>
                    <input type="file" name="document" required>
                    @error('document') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Category</span>
                    <input type="text" name="category" value="{{ old('category') }}" placeholder="e.g. pleading, letter, evidence" data-no-money>
                    @error('category') <small>{{ $message }}</small> @enderror
                </label>
            </div>
            <div class="kfms-form-actions">
                <button type="submit"><i class="mdi mdi-upload"></i> Upload Document</button>
            </div>
        </form>
    </section>
@endsection
