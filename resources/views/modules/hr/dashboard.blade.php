@extends('layouts.admin')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')

@section('content')
    <section class="kfms-dashboard-hero kfms-hr-dashboard-hero">
        <div>
            <span>Human resources control room</span>
            <h2>Manage staff access, leave, and team records.</h2>
            <p>HR users track active staff, pending access, department coverage, branch headcount, and leave requests from one place.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('staff.create')
                <a class="kfms-btn" href="{{ route('staff.create') }}">
                    <i class="mdi mdi-account-plus-outline"></i>
                    New Staff
                </a>
            @endcan
            @can('staff.index')
                <a class="kfms-link-btn" href="{{ route('staff.index') }}">
                    <i class="mdi mdi-account-group-outline"></i>
                    Staff Register
                </a>
            @endcan
            @can('leave.index')
                <a class="kfms-link-btn" href="{{ route('leave.index') }}">
                    <i class="mdi mdi-calendar-account-outline"></i>
                    Leave Management
                </a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        @foreach ($stats as $stat)
            <section class="kfms-card kfms-stat-card">
                <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                <span class="kfms-stat-body">
                    <span class="kfms-card-label">{{ $stat['label'] }}</span>
                    <strong class="kfms-stat">{{ number_format($stat['value']) }}</strong>
                </span>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Headcount Coverage</h2>
                <span>Staff distribution by department and branch</span>
            </div>
        </div>

        <div class="kfms-grid-two">
            <div class="kfms-bordered-list">
                <h3>Department Headcount</h3>
                @forelse ($departmentHeadcount as $department)
                    <div>
                        <span>{{ $department->name }}</span>
                        <strong>{{ number_format($department->active_staff_count) }}</strong>
                    </div>
                @empty
                    <p class="kfms-empty">No departments yet.</p>
                @endforelse
            </div>
            <div class="kfms-bordered-list">
                <h3>Branch Headcount</h3>
                @forelse ($branchHeadcount as $branch)
                    <div>
                        <span>{{ $branch->name }}</span>
                        <strong>{{ number_format($branch->active_staff_count) }}</strong>
                    </div>
                @empty
                    <p class="kfms-empty">No branches yet.</p>
                @endforelse
            </div>
        </div>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Staff Profiles</h2>
                    <span>Latest staff records in the system</span>
                </div>
                @can('staff.index')
                    <a class="kfms-link-btn" href="{{ route('staff.index') }}">View all <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentStaff as $person)
                            <tr>
                                <td>
                                    <strong>{{ $person->name }}</strong><br>
                                    <span>{{ $person->staffProfile?->staff_no ?: $person->email }}</span>
                                </td>
                                <td>{{ $person->department?->name ?: '-' }}</td>
                                <td>
                                    <span class="kfms-status is-{{ $person->staffProfile?->employment_status ?: 'muted' }}">
                                        {{ ucfirst($person->staffProfile?->employment_status ?: 'Unknown') }}
                                    </span>
                                </td>
                                <td>
                                    @can('staff.show')
                                        <a class="kfms-link-btn" href="{{ route('staff.show', $person) }}">View</a>
                                    @else
                                        -
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No staff profiles yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Leave Calendar</h2>
                    <span>Approved and submitted leave from today onward</span>
                </div>
                @can('leave.index')
                    <a class="kfms-link-btn" href="{{ route('leave.index') }}">Review leave <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($upcomingLeave as $leave)
                            <tr>
                                <td>{{ $leave->user?->name ?: '-' }}</td>
                                <td>{{ $leave->leaveType?->name ?: '-' }}</td>
                                <td>{{ $leave->start_date?->format('d M Y') }} to {{ $leave->end_date?->format('d M Y') }}</td>
                                <td><span class="kfms-status kfms-status-{{ $leave->status }}">{{ $leave->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No upcoming leave.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
