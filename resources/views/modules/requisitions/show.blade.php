@extends('layouts.admin')

@section('title', 'Requisition')
@section('page-title', 'Requisition')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $requisition->reference_no }}</h2>
                    <span><span class="kfms-status kfms-status-{{ $requisition->status }}">{{ $requisition->statusLabel() }}</span></span>
                </div>
                <a class="kfms-link-btn" href="{{ route('requisitions.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Requisitions
                </a>
            </div>

            <dl class="kfms-detail-list">
                <div><dt>Requested By</dt><dd>{{ $requisition->requester?->name }}</dd></div>
                <div><dt>Category</dt><dd>{{ $requisition->category?->name ?: '-' }}</dd></div>
                <div><dt>Related Matter</dt><dd>{{ $requisition->matter ? $requisition->matter->reference_no.' — '.$requisition->matter->title : '-' }}</dd></div>
                <div><dt>Amount</dt><dd>{{ number_format($requisition->amount, 2) }}</dd></div>
                <div><dt>Purpose</dt><dd>{{ $requisition->purpose }}</dd></div>
                <div><dt>Notes</dt><dd>{{ $requisition->notes ?: '-' }}</dd></div>
                <div><dt>Reviewed By</dt><dd>{{ $requisition->reviewer?->name ?: '-' }}</dd></div>
                <div><dt>Review Notes</dt><dd>{{ $requisition->review_notes ?: '-' }}</dd></div>
            </dl>

            @if ($requisition->attachments->isNotEmpty())
                <div class="kfms-panel-subheader"><h3>Attachments</h3></div>
                <ul class="kfms-file-list">
                    @foreach ($requisition->attachments as $attachment)
                        <li><i class="mdi mdi-paperclip"></i> <a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif

            @if ($requisition->requested_by === auth()->id() && $requisition->status === 'submitted')
                <form method="POST" action="{{ route('requisitions.cancel', $requisition) }}" class="kfms-form-actions" onsubmit="return confirm('Cancel this requisition?');">
                    @csrf
                    @method('PATCH')
                    <button class="kfms-link-btn kfms-link-btn-danger" type="submit">Cancel Requisition</button>
                </form>
            @endif
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Approval</h2>
            </div>

            @if ($canApprove && $requisition->status === 'submitted')
                <form class="kfms-form" method="POST" action="{{ route('requisitions.approve', $requisition) }}">
                    @csrf
                    @method('PATCH')
                    <label class="kfms-span-2">
                        <span>Decision Notes (optional)</span>
                        <textarea name="review_notes" rows="2">{{ old('review_notes') }}</textarea>
                    </label>
                    <div class="kfms-form-actions">
                        <button class="kfms-btn" type="submit"><i class="mdi mdi-check"></i> Approve</button>
                    </div>
                </form>
                <form class="kfms-form" method="POST" action="{{ route('requisitions.reject', $requisition) }}">
                    @csrf
                    @method('PATCH')
                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn kfms-link-btn-danger" type="submit"><i class="mdi mdi-close"></i> Reject</button>
                    </div>
                </form>
            @elseif ($requisition->status === 'submitted')
                <p class="kfms-muted">Awaiting approval.</p>
            @else
                <p class="kfms-muted">This requisition is {{ strtolower($requisition->statusLabel()) }}.</p>
            @endif

            @if ($requisition->approvals->isNotEmpty())
                <div class="kfms-panel-subheader"><h3>Approval Trail</h3></div>
                @include('modules.partials.table', [
                    'headers' => ['Level', 'Approver', 'Status', 'Decided'],
                    'rows' => $requisition->approvals->map(fn ($a) => [
                        $a->level,
                        $a->approver?->name ?: 'Pending',
                        ucfirst($a->status),
                        $a->decided_at?->format('d M Y H:i'),
                    ]),
                ])
            @endif
        </section>
    </div>
@endsection
