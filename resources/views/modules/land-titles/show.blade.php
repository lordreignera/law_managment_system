@extends('layouts.admin')

@section('title', 'Security Details')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $title->reference_no }}</h2>
                <span>{{ $title->borrower_name }} - {{ $title->statusLabel() }}</span>
            </div>
            <div class="kfms-row-actions">
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back
                </a>
                <a class="kfms-btn" href="{{ route('land-titles.edit', $title) }}">
                    <i class="mdi mdi-pencil"></i>
                    Edit
                </a>
                @if (! in_array($title->status, ['returned', 'closed'], true))
                    <a class="kfms-link-btn" href="{{ route('land-titles.return.form', $title) }}">
                        <i class="mdi mdi-keyboard-return"></i>
                        Return Security
                    </a>
                @endif
                <form method="POST" action="{{ route('land-titles.destroy', $title) }}">
                    @csrf
                    @method('DELETE')
                    <button class="kfms-link-btn kfms-danger" type="submit" onclick="return confirm('Delete this security?')">
                        <i class="mdi mdi-delete-outline"></i>
                        Delete
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Borrower</span>
                <strong>{{ $title->borrower_name }}</strong>
            </div>
            <div>
                <span>Status</span>
                <strong>{{ $title->statusLabel() }}</strong>
            </div>
            <div>
                <span>Bank / Financial Institution</span>
                <strong>{{ $title->bank?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Bank Branch / Source Office</span>
                <strong>{{ $title->bankBranch?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>MZO / Zonal Office</span>
                <strong>{{ $title->zonalOffice?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>MZO Location</span>
                <strong>{{ $title->zonalOffice?->office_location ?: '-' }}</strong>
            </div>
            <div>
                <span>Instruction Type</span>
                <strong>{{ $title->instruction_type ?: '-' }}</strong>
            </div>
            <div>
                <span>Handled By</span>
                <strong>{{ $title->handler?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Instruction Date</span>
                <strong>{{ $title->instruction_date?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Received From</span>
                <strong>{{ $title->received_from ?: '-' }}</strong>
            </div>
            <div>
                <span>Returned To</span>
                <strong>{{ $title->returned_to ?: '-' }}</strong>
            </div>
            <div>
                <span>Date &amp; Time Received</span>
                <strong>{{ $title->received_at?->format('d M Y, H:i') ?: '-' }}</strong>
            </div>
            <div>
                <span>Date &amp; Time Dispatched</span>
                <strong>{{ $title->dispatched_at?->format('d M Y, H:i') ?: '-' }}</strong>
            </div>
            <div>
                <span>Date &amp; Time Returned</span>
                <strong>{{ $title->returned_at?->format('d M Y, H:i') ?: '-' }}</strong>
            </div>
            <div class="kfms-span-2">
                <span>Linked Matter</span>
                <strong>
                    @if ($title->matter)
                        <a href="{{ route('matters.show', $title->matter) }}">{{ $title->matter->reference_no }} - {{ $title->matter->title }}</a>
                    @else
                        -
                    @endif
                </strong>
            </div>
            <div class="kfms-span-2">
                <span>Notes</span>
                <strong>{{ $title->notes ?: '-' }}</strong>
            </div>
        </div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Documents</h2>
                <span>Uploaded securities support documents</span>
            </div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Uploaded By</th>
                        <th>Uploaded On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($title->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->original_name }}</td>
                            <td>{{ $attachment->uploader?->name ?: '-' }}</td>
                            <td>{{ $attachment->created_at?->format('d M Y, H:i') }}</td>
                            <td>
                                <a class="kfms-link-btn" href="{{ route('attachments.download', $attachment) }}">
                                    <i class="mdi mdi-download"></i>
                                    Download
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="kfms-empty">No documents uploaded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
