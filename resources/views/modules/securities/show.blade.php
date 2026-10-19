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
                <a class="kfms-link-btn" href="{{ route('securities.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
                @if ($title->status !== 'archived') @can('securities.edit')<a class="kfms-btn" href="{{ route('securities.edit', $title) }}"><i class="mdi mdi-pencil"></i> Edit</a>@endcan @endif
                @if ($title->status === 'dispatched')
                    @can('securities.return.form')<a class="kfms-link-btn" href="{{ route('securities.return.form', $title) }}"><i class="mdi mdi-keyboard-return"></i> Return Security</a>@endcan
                @endif
                @if (in_array($title->status, ['returned', 'closed'], true)) @can('securities.archive')<form method="POST" action="{{ route('securities.archive', $title) }}">
                    @csrf
                    @method('PATCH')
                    <button class="kfms-link-btn kfms-danger" type="submit" onclick="return confirm('Archive this security? Its custody history will be retained.')"><i class="mdi mdi-archive-outline"></i> Archive</button>
                </form>@endcan @endif
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div><span>Borrower</span><strong>{{ $title->borrower_name }}</strong></div>
            <div><span>Borrower Contact</span><strong>{{ $title->borrower_contact ?: '-' }}</strong></div>
            <div><span>Client</span><strong>{{ $title->client?->display_name ?: '-' }}</strong></div>
            <div><span>Status</span><strong>{{ $title->statusLabel() }}</strong></div>
            <div><span>Direction</span><strong>{{ str($title->movement_direction)->headline() }}</strong></div>
            <div><span>Security Type</span><strong>{{ str($title->security_type)->headline() }}</strong></div>
            <div><span>Title Number</span><strong>{{ $title->title_number ?: '-' }}</strong></div>
            <div><span>Property Reference</span><strong>{{ $title->land_reference ?: '-' }}</strong></div>
            <div><span>Tenure</span><strong>{{ $title->tenure ?: '-' }}</strong></div>
            <div><span>District</span><strong>{{ $title->district ?: '-' }}</strong></div>
            <div><span>Facility / Account Number</span><strong>{{ $title->facility_account_no ?: '-' }}</strong></div>
            <div><span>Bank / Financial Institution</span><strong>{{ $title->bank?->name ?: '-' }}</strong></div>
            <div><span>Bank Branch / Source Office</span><strong>{{ $title->bankBranch?->name ?: '-' }}</strong></div>
            <div><span>MZO / Zonal Office</span><strong>{{ $title->zonalOffice?->name ?: '-' }}</strong></div>
            <div><span>MZO Location</span><strong>{{ $title->zonalOffice?->office_location ?: '-' }}</strong></div>
            <div><span>Instruction Type</span><strong>{{ $title->instruction_type ?: '-' }}</strong></div>
            <div><span>Handled By</span><strong>{{ $title->handler?->name ?: '-' }}</strong></div>
            <div><span>Instruction Date</span><strong>{{ $title->instruction_date?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Received From</span><strong>{{ $title->received_from ?: '-' }}</strong></div>
            <div><span>Dispatched To</span><strong>{{ $title->dispatched_to ?: '-' }}</strong></div>
            <div><span>Returned To</span><strong>{{ $title->returned_to ?: '-' }}</strong></div>
            <div><span>Date &amp; Time Received</span><strong>{{ $title->received_at?->format('d M Y, H:i') ?: '-' }}</strong></div>
            <div><span>Date &amp; Time Dispatched</span><strong>{{ $title->dispatched_at?->format('d M Y, H:i') ?: '-' }}</strong></div>
            <div><span>Dispatch Acknowledgement</span><strong>{{ $title->dispatch_acknowledged_by ?: '-' }}{{ $title->dispatch_acknowledged_at ? ' @ '.$title->dispatch_acknowledged_at->format('d M Y, H:i') : '' }}</strong></div>
            <div><span>Date &amp; Time Returned</span><strong>{{ $title->returned_at?->format('d M Y, H:i') ?: '-' }}</strong></div>
            <div class="kfms-span-2"><span>Linked Matter</span><strong>@if ($title->matter)<a href="{{ route('matters.show', $title->matter) }}">{{ $title->matter->reference_no }} - {{ $title->matter->title }}</a>@else - @endif</strong></div>
            <div class="kfms-span-2"><span>Instruction Details</span><strong>{{ $title->instruction_details ?: '-' }}</strong></div>
            <div class="kfms-span-2"><span>Notes</span><strong>{{ $title->notes ?: '-' }}</strong></div>
        </div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Custody Movement History</h2><span>Every receipt, dispatch, return, and transfer is recorded.</span></div></div>
        @can('securities.movements.store')
            <form class="kfms-form" method="POST" action="{{ route('securities.movements.store', $title) }}">
                @csrf
                <div class="kfms-form-grid">
                    <label><span>Movement</span><select name="movement_type" required>@foreach ($movementTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label><span>Moved At</span><input type="datetime-local" name="moved_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></label>
                    <label><span>From</span><input type="text" name="from_party"></label>
                    <label><span>To</span><input type="text" name="to_party"></label>
                    <label><span>From Staff</span><select name="from_user_id"><option value="">External / not staff</option>@foreach($handlers as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></label>
                    <label><span>To Staff</span><select name="to_user_id"><option value="">External / not staff</option>@foreach($handlers as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></label>
                    <label><span>Acknowledged By</span><input type="text" name="acknowledged_by"></label>
                    <label><span>Acknowledged At</span><input type="datetime-local" name="acknowledged_at"></label>
                    <label class="kfms-span-2"><span>Notes</span><textarea name="notes" rows="2"></textarea></label>
                </div>
                <button type="submit">Record Movement</button>
            </form>
        @endcan
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>When</th><th>Movement</th><th>From</th><th>To</th><th>Staff handover</th><th>Acknowledged</th><th>Recorded By</th><th>Notes</th></tr></thead><tbody>
            @forelse ($title->movements as $movement)
                <tr>
                    <td>{{ $movement->moved_at?->format('d M Y, H:i') }}</td>
                    <td>{{ $movement->typeLabel() }}</td>
                    <td>{{ $movement->from_party ?: '-' }}</td>
                    <td>{{ $movement->to_party ?: '-' }}</td>
                    <td>{{ collect([$movement->fromUser?->name, $movement->toUser?->name])->filter()->join(' -> ') ?: '-' }}</td>
                    <td>{{ $movement->acknowledged_by ?: '-' }}{{ $movement->acknowledged_at ? ' @ '.$movement->acknowledged_at->format('d M Y H:i') : '' }}</td>
                    <td>{{ $movement->recorder?->name ?: '-' }}</td>
                    <td>{{ $movement->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="kfms-empty">No movement history recorded.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Documentation Checklist</h2><span>Required documents and completion sign-off.</span></div></div>
        @can('securities.checklist.store')
            <form class="kfms-form" method="POST" action="{{ route('securities.checklist.store', $title) }}">
                @csrf
                <div class="kfms-form-grid"><label><span>Checklist Item</span><input type="text" name="label" placeholder="e.g. Search report attached" required></label><label><span>Required</span><select name="is_required"><option value="1">Yes</option><option value="0">No</option></select></label></div>
                <button type="submit">Add Checklist Item</button>
            </form>
        @endcan
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Item</th><th>Required</th><th>Status</th><th>Completed By</th><th>Completed At</th><th>Evidence</th><th>Action</th></tr></thead><tbody>
            @forelse ($title->checklistItems as $item)
                <tr><td>{{ $item->label }}@if($item->notes)<br><span class="kfms-muted">{{ $item->notes }}</span>@endif</td><td>{{ $item->is_required ? 'Yes' : 'No' }}</td><td>{{ $item->statusLabel() }}</td><td>{{ $item->completer?->name ?: '-' }}</td><td>{{ $item->completed_at?->format('d M Y, H:i') ?: '-' }}</td><td>@if($item->evidenceAttachment)<a href="{{ route('attachments.download', $item->evidenceAttachment) }}">{{ $item->evidenceAttachment->original_name }}</a>@else - @endif</td><td>@can('securities.checklist.update')<form method="POST" action="{{ route('securities.checklist.update', [$title, $item]) }}" class="kfms-inline-form">@csrf @method('PATCH')<select name="status">@foreach($checklistStatuses as $status => $label)<option value="{{ $status }}" @selected($item->status === $status)>{{ $label }}</option>@endforeach</select><select name="evidence_attachment_id"><option value="">No evidence</option>@foreach($title->attachments as $attachment)<option value="{{ $attachment->id }}" @selected($item->evidence_attachment_id === $attachment->id)>{{ $attachment->original_name }}</option>@endforeach</select><input type="text" name="notes" value="{{ $item->notes }}" placeholder="Note"><button class="kfms-link-btn" type="submit">Save</button></form>@endcan @if(!$item->is_required) @can('securities.checklist.destroy')<form method="POST" action="{{ route('securities.checklist.destroy', [$title, $item]) }}" class="kfms-inline-form">@csrf @method('DELETE')<button class="kfms-link-btn kfms-danger" type="submit">Remove</button></form>@endcan @endif</td></tr>
            @empty
                <tr><td colspan="7" class="kfms-empty">No checklist items recorded.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Milestones and Alerts</h2><span>Track due work and overdue security actions.</span></div></div>
        @can('securities.milestones.store')
            <form class="kfms-form" method="POST" action="{{ route('securities.milestones.store', $title) }}">
                @csrf
                <div class="kfms-form-grid"><label><span>Milestone</span><input type="text" name="name" placeholder="e.g. Lodge mortgage" required></label><label><span>Due At</span><input type="datetime-local" name="due_at"></label><label><span>Reminder Days Before</span><input type="number" name="reminder_days_before" min="0" max="365" value="1" required></label><label class="kfms-span-2"><span>Notes</span><textarea name="notes" rows="2"></textarea></label></div>
                <button type="submit">Add Milestone</button>
            </form>
        @endcan
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Milestone</th><th>Due</th><th>Status</th><th>Completed By</th><th>Action</th></tr></thead><tbody>
            @forelse ($title->milestones as $milestone)
                <tr><td>{{ $milestone->name }}@if($milestone->notes)<br><span class="kfms-muted">{{ $milestone->notes }}</span>@endif</td><td>{{ $milestone->due_at?->format('d M Y, H:i') ?: '-' }}</td><td>{{ $milestone->statusLabel() }}</td><td>{{ $milestone->completer?->name ?: '-' }}</td><td>@can('securities.milestones.update')<form method="POST" action="{{ route('securities.milestones.update', [$title, $milestone]) }}" class="kfms-inline-form">@csrf @method('PATCH')<select name="status">@foreach($milestoneStatuses as $status => $label)<option value="{{ $status }}" @selected($milestone->status === $status)>{{ $label }}</option>@endforeach</select><input type="datetime-local" name="due_at" value="{{ $milestone->due_at?->format('Y-m-d\TH:i') }}"><input type="number" name="reminder_days_before" value="{{ $milestone->reminder_days_before }}" min="0" max="365"><input type="text" name="notes" value="{{ $milestone->notes }}" placeholder="Note"><button class="kfms-link-btn" type="submit">Save</button></form>@endcan @if($milestone->status !== 'complete') @can('securities.milestones.destroy')<form method="POST" action="{{ route('securities.milestones.destroy', [$title, $milestone]) }}" class="kfms-inline-form">@csrf @method('DELETE')<button class="kfms-link-btn kfms-danger" type="submit">Remove</button></form>@endcan @endif</td></tr>
            @empty
                <tr><td colspan="5" class="kfms-empty">No milestones recorded.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header"><div><h2>Documents</h2><span>Uploaded securities support documents</span></div></div>
        <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>File</th><th>Uploaded By</th><th>Uploaded On</th><th>Action</th></tr></thead><tbody>
            @forelse ($title->attachments as $attachment)
                <tr><td>{{ $attachment->original_name }}</td><td>{{ $attachment->uploader?->name ?: '-' }}</td><td>{{ $attachment->created_at?->format('d M Y, H:i') }}</td><td><a class="kfms-link-btn" href="{{ route('attachments.download', $attachment) }}"><i class="mdi mdi-download"></i> Download</a></td></tr>
            @empty
                <tr><td colspan="4" class="kfms-empty">No documents uploaded yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
