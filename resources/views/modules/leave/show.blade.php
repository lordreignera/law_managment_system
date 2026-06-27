@extends('layouts.admin')

@section('title', 'Leave Request')
@section('page-title', 'Leave Request')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $leave->leave_no }}</h2>
                    <span><span class="kfms-status kfms-status-{{ $leave->status }}">{{ $leave->statusLabel() }}</span></span>
                </div>
                <a class="kfms-link-btn" href="{{ route('leave.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Leave
                </a>
            </div>

            <dl class="kfms-detail-list">
                <div><dt>Staff</dt><dd>{{ $leave->user?->name }}</dd></div>
                <div><dt>Leave Type</dt><dd>{{ $leave->leaveType?->name ?: '-' }}</dd></div>
                <div><dt>Start Date</dt><dd>{{ $leave->start_date?->format('d M Y') }}</dd></div>
                <div><dt>End Date</dt><dd>{{ $leave->end_date?->format('d M Y') }}</dd></div>
                <div><dt>Days</dt><dd>{{ rtrim(rtrim(number_format($leave->days, 1), '0'), '.') }}</dd></div>
                <div><dt>Reason</dt><dd>{{ $leave->reason ?: '-' }}</dd></div>
                <div><dt>Reviewed By</dt><dd>{{ $leave->reviewer?->name ?: '-' }}</dd></div>
                <div><dt>Review Notes</dt><dd>{{ $leave->review_notes ?: '-' }}</dd></div>
            </dl>

            @if ($leave->attachments->isNotEmpty())
                <div class="kfms-panel-subheader"><h3>Attachments</h3></div>
                <ul class="kfms-file-list">
                    @foreach ($leave->attachments as $attachment)
                        <li><i class="mdi mdi-paperclip"></i> {{ $attachment->original_name }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($leave->user_id === auth()->id() && $leave->status === 'submitted')
                <form method="POST" action="{{ route('leave.cancel', $leave) }}" class="kfms-form-actions" onsubmit="return confirm('Cancel this leave request?');">
                    @csrf
                    @method('PATCH')
                    <button class="kfms-link-btn kfms-link-btn-danger" type="submit">Cancel Request</button>
                </form>
            @endif
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Approval</h2>
            </div>

            @if ($canApprove && $leave->status === 'submitted')
                <form class="kfms-form" method="POST" action="{{ route('leave.approve', $leave) }}">
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
                <form class="kfms-form" method="POST" action="{{ route('leave.reject', $leave) }}">
                    @csrf
                    @method('PATCH')
                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn kfms-link-btn-danger" type="submit"><i class="mdi mdi-close"></i> Reject</button>
                    </div>
                </form>
            @elseif ($leave->status === 'submitted')
                <p class="kfms-muted">Awaiting approval.</p>
            @else
                <p class="kfms-muted">This request is {{ strtolower($leave->statusLabel()) }}.</p>
            @endif

            @if ($leave->approvals->isNotEmpty())
                <div class="kfms-panel-subheader"><h3>Approval Trail</h3></div>
                @include('modules.partials.table', [
                    'headers' => ['Level', 'Approver', 'Status', 'Decided'],
                    'rows' => $leave->approvals->map(fn ($a) => [
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
