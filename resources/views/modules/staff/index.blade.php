@extends('layouts.admin')

@section('title', 'Staff')
@section('page-title', 'Staff')

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
                <h2>Staff Register</h2>
                <span>{{ $staff->total() }} records</span>
            </div>
            @can('hr.dashboard')
                <div class="kfms-toolbar-actions">
                    @can('staff.create')
                        <a class="kfms-btn" href="{{ route('staff.create') }}">
                            <i class="mdi mdi-account-plus-outline"></i>
                            New Staff
                        </a>
                    @endcan
                    <a class="kfms-link-btn" href="{{ route('hr.dashboard') }}">
                        <i class="mdi mdi-arrow-left"></i>
                        HR Dashboard
                    </a>
                </div>
            @endcan
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('staff.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search staff no, name, email, phone, branch, department, or role">
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
                <span>Branch</span>
                <select name="branch_id">
                    <option value="">All Branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Department</span>
                <select name="department_id">
                    <option value="">All Departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('staff.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Contact</th>
                        <th>Branch</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong><br>
                                <span>{{ $user->staffProfile?->staff_no ?: 'No staff no' }}</span>
                            </td>
                            <td>
                                {{ $user->email }}<br>
                                <span>{{ $user->staffProfile?->phone ?: '-' }}</span>
                            </td>
                            <td>{{ $user->branch?->name ?: '-' }}</td>
                            <td>{{ $user->department?->name ?: '-' }}</td>
                            <td>{{ $user->roles->pluck('name')->join(', ') ?: '-' }}</td>
                            <td>
                                <span class="kfms-status is-{{ $user->staffProfile?->employment_status ?: 'muted' }}">
                                    {{ $statuses[$user->staffProfile?->employment_status] ?? 'Unknown' }}
                                </span>
                            </td>
                            <td>
                                <div class="kfms-table-actions">
                                    @can('staff.show')
                                        <a class="kfms-link-btn" href="{{ route('staff.show', $user) }}">View</a>
                                    @endcan
                                    @can('staff.edit')
                                        <a class="kfms-link-btn" href="{{ route('staff.edit', $user) }}">Edit</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No staff records match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $staff->links() }}
    </section>
@endsection
