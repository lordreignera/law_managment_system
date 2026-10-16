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
                @else
                    <a class="kfms-btn" href="{{ route('clients.matters.create', $file->client) }}">
                        <i class="mdi mdi-briefcase-plus"></i>
                        Link to Matter
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
                        <th>Version</th>
                        <th>Visibility</th>
                        <th>Uploaded By</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($file->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->title ?: $attachment->original_name }}</td>
                            <td>{{ str($attachment->category ?: 'file-document')->headline() }}</td>
                            <td>v{{ $attachment->version_number }} @if ($attachment->is_current)<span class="kfms-status kfms-status-approved">Current</span>@endif</td>
                            <td>{{ $attachment->is_client_visible ? 'Client visible' : 'Internal' }}</td>
                            <td>{{ $attachment->uploader?->name ?: '-' }}</td>
                            <td>{{ number_format(($attachment->size ?? 0) / 1024, 1) }} KB</td>
                            <td>{{ $attachment->created_at?->format('d M Y, H:i') }}</td>
                            <td><a class="kfms-link-btn" href="{{ route('attachments.download', $attachment) }}"><i class="mdi mdi-download"></i> Download</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kfms-empty">No documents uploaded for this file yet.</td>
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
                <label>
                    <span>Document Title</span>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="191">
                </label>
                <label>
                    <span>Document Key</span>
                    <input type="text" name="document_key" value="{{ old('document_key') }}" placeholder="e.g. engagement-letter">
                </label>
                <label>
                    <span>Replace Current Version</span>
                    <select name="replace_document_id">
                        <option value="">New document</option>
                        @foreach ($file->attachments->where('is_current', true) as $currentDocument)
                            <option value="{{ $currentDocument->id }}">{{ $currentDocument->title ?: $currentDocument->original_name }} (v{{ $currentDocument->version_number }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="kfms-check-row"><input type="checkbox" name="is_client_visible" value="1" @checked(old('is_client_visible'))><span>Share current version with client</span></label>
            </div>
            <div class="kfms-form-actions">
                <button type="submit"><i class="mdi mdi-upload"></i> Upload Document</button>
            </div>
        </form>
    </section>
@endsection
