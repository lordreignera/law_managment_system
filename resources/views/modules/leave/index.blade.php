@extends('layouts.admin')

@section('title', 'Leave')
@section('page-title', 'Leave Management')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ number_format($value) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $canApprove ? 'Leave Requests (HR Review)' : 'My Leave Requests' }}</h2>
                <span>{{ $leave->total() }} records</span>
            </div>
            <a class="kfms-btn" href="{{ route('leave.create') }}">
                <i class="mdi mdi-plus"></i>
                New Leave Request
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->has('leave'))
            <div class="kfms-alert kfms-alert-danger">{{ $errors->first('leave') }}</div>
        @endif

        @if ($annualLeavePlan && $annualLeavePlan['remaining_days'] > 0)
            <div class="kfms-alert">
                <strong>Annual leave reminder for {{ $annualLeavePlan['year'] }}.</strong>
                You have scheduled {{ rtrim(rtrim(number_format($annualLeavePlan['scheduled_days'], 1), '0'), '.') }} of
                {{ $annualLeavePlan['required_days'] }} days. Please schedule the remaining
                {{ rtrim(rtrim(number_format($annualLeavePlan['remaining_days'], 1), '0'), '.') }} days.
                <a class="kfms-link-btn" href="{{ route('leave.create') }}">Schedule leave</a>
            </div>
        @endif

        @if ($leaveBalances->isNotEmpty())
            <div class="kfms-table-wrap" style="margin-bottom: 1rem">
                <table class="kfms-table">
                    <thead><tr><th>Leave balance ({{ now()->year }})</th><th>Entitlement</th><th>Scheduled / used</th><th>Remaining</th></tr></thead>
                    <tbody>
                        @foreach ($leaveBalances as $balance)
                            <tr>
                                <td>{{ $balance['type']->name }}</td>
                                <td>{{ rtrim(rtrim(number_format($balance['entitlement'], 1), '0'), '.') }} days</td>
                                <td>{{ rtrim(rtrim(number_format($balance['used'], 1), '0'), '.') }} days</td>
                                <td><strong>{{ rtrim(rtrim(number_format($balance['remaining'], 1), '0'), '.') }} days</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('leave.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search leave no or staff">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Leave Type</span>
                <select name="leave_type_id">
                    <option value="">All Types</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('leave.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Leave No</th>
                        @if ($canApprove)<th>Staff</th>@endif
                        <th>Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leave as $request)
                        <tr>
                            <td>{{ $request->leave_no }}</td>
                            @if ($canApprove)<td>{{ $request->user?->name }}</td>@endif
                            <td>{{ $request->leaveType?->name ?: '-' }}</td>
                            <td>{{ $request->start_date?->format('d M Y') }} &ndash; {{ $request->end_date?->format('d M Y') }}</td>
                            <td>{{ rtrim(rtrim(number_format($request->days, 1), '0'), '.') }}</td>
                            <td><span class="kfms-status kfms-status-{{ $request->status }}">{{ $request->statusLabel() }}</span></td>
                            <td><a class="kfms-link-btn" href="{{ route('leave.show', $request) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canApprove ? 7 : 6 }}" class="kfms-empty">No leave requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $leave->links() }}
    </section>
@endsection
