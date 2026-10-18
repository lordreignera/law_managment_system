@extends('layouts.admin')

@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Attendance</h2>
                <span>Check-in, check-out, corrections, and daily staff coverage.</span>
            </div>
            <form class="kfms-calendar-jump" method="GET" action="{{ route('attendance.index') }}">
                <input type="date" name="date" value="{{ $date->toDateString() }}" aria-label="Attendance date">
                <button class="kfms-link-btn" type="submit">View date</button>
            </form>
        </div>

        @if (session('status'))<div class="kfms-alert">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>@endif

        <div class="kfms-stat-grid">
            @foreach ($summary as $label => $value)
                <section class="kfms-card"><span class="kfms-card-label">{{ $label }}</span><strong class="kfms-stat">{{ $value }}</strong></section>
            @endforeach
        </div>

        <section class="kfms-panel kfms-attendance-today-panel">
            <div class="kfms-panel-header">
                <div><h3>My attendance today</h3><span>{{ today()->format('d M Y') }}</span></div>
                <div class="kfms-toolbar-actions">
                    @if (! $todayRecord?->checked_in_at)
                        <form method="POST" action="{{ route('attendance.check-in') }}">@csrf<button class="kfms-btn" type="submit"><i class="mdi mdi-login"></i> Check in</button></form>
                    @elseif (! $todayRecord?->checked_out_at)
                        <form method="POST" action="{{ route('attendance.check-out') }}">@csrf<button class="kfms-btn" type="submit"><i class="mdi mdi-logout"></i> Check out</button></form>
                    @else
                        <span class="kfms-status kfms-status-approved">Completed</span>
                    @endif
                </div>
            </div>
            <div class="kfms-detail-grid">
                <div><dt>Check-in</dt><dd>{{ $todayRecord?->checked_in_at?->format('d M Y H:i') ?: 'Not recorded' }}</dd></div>
                <div><dt>Check-out</dt><dd>{{ $todayRecord?->checked_out_at?->format('d M Y H:i') ?: 'Not recorded' }}</dd></div>
                <div><dt>Status</dt><dd>{{ $todayRecord?->statusLabel() ?: 'Not recorded' }}</dd></div>
                <div><dt>Device/IP captured</dt><dd>{{ $todayRecord?->check_in_ip ?: 'Recorded at check-in' }}</dd></div>
            </div>
        </section>

        @if ($canReport)
            <section class="kfms-panel">
                <div class="kfms-panel-header"><div><h3>Daily staff report</h3><span>Late and absent staff are calculated against the configured start time and grace period.</span></div></div>
                <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Staff</th><th>Department</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead><tbody>
                    @forelse ($rows as $row)
                        <tr><td><strong>{{ $row['staffMember']->name }}</strong><br><small>{{ $row['staffMember']->staffProfile?->staff_no ?: $row['staffMember']->email }}</small></td><td>{{ $row['staffMember']->department?->name ?: '-' }}</td><td>{{ $row['record']?->checked_in_at?->format('H:i') ?: '-' }}</td><td>{{ $row['record']?->checked_out_at?->format('H:i') ?: '-' }}</td><td><span class="kfms-status kfms-status-{{ $row['status'] }}">{{ str($row['status'])->headline() }}</span></td></tr>
                    @empty<tr><td colspan="5" class="kfms-empty">No active staff found in this scope.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @endif

        <section class="kfms-panel">
            <div class="kfms-panel-header"><div><h3>Request a correction</h3><span>Corrections retain the original record and require manager approval.</span></div></div>
            <form class="kfms-form" method="POST" action="{{ route('attendance.corrections.store') }}">
                @csrf
                <div class="kfms-form-grid">
                    <label><span>Date</span><input type="date" name="attendance_date" value="{{ old('attendance_date', $date->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                    <label><span>Corrected check-in</span><input type="datetime-local" name="requested_check_in_at" value="{{ old('requested_check_in_at') }}"></label>
                    <label><span>Corrected check-out</span><input type="datetime-local" name="requested_check_out_at" value="{{ old('requested_check_out_at') }}"></label>
                    <label class="kfms-span-2"><span>Reason</span><textarea name="reason" rows="2" required>{{ old('reason') }}</textarea></label>
                </div>
                <button class="kfms-link-btn" type="submit"><i class="mdi mdi-file-edit-outline"></i> Submit correction</button>
            </form>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header"><div><h3>{{ $canReport ? 'Pending correction approvals' : 'My correction requests' }}</h3><span>All decisions are included in the attendance audit trail.</span></div></div>
            <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Date</th><th>Staff</th><th>Requested times</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody>
                @forelse ($corrections as $correction)
                    <tr><td>{{ $correction->attendance_date?->format('d M Y') }}</td><td>{{ $correction->user?->name }}</td><td>{{ $correction->requested_check_in_at?->format('H:i') ?: '-' }} – {{ $correction->requested_check_out_at?->format('H:i') ?: '-' }}</td><td>{{ $correction->reason }}</td><td><span class="kfms-status kfms-status-{{ $correction->status }}">{{ $correction->statusLabel() }}</span></td><td>@if($canReport && $correction->status === 'submitted')<form method="POST" action="{{ route('attendance.corrections.approve', $correction) }}" class="kfms-inline-form">@csrf @method('PATCH')<button class="kfms-link-btn" type="submit">Approve</button></form><form method="POST" action="{{ route('attendance.corrections.reject', $correction) }}" class="kfms-inline-form">@csrf @method('PATCH')<button class="kfms-link-btn" type="submit">Reject</button></form>@else - @endif</td></tr>
                @empty<tr><td colspan="6" class="kfms-empty">No correction requests.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </section>
@endsection
