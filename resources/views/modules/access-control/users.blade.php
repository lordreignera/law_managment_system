@extends('layouts.admin')

@section('title', 'All Users')
@section('page-title', 'Access Control')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>All Users</h2>
                <span>Assign roles, approve access, suspend users, or remove accounts.</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('access.users.create')
                    <a class="kfms-btn" href="{{ route('access.users.create') }}">
                        <i class="mdi mdi-account-plus-outline"></i>
                        Add User
                    </a>
                @endcan
                <a class="kfms-link-btn" href="{{ route('access.approvals.index') }}">
                    <i class="mdi mdi-account-clock-outline"></i>
                    Approval Requests
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('access.users.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, or role">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach (['pending', 'active', 'inactive', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Role</span>
                <select name="role">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('access.users.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Branch</th>
                        <th>Department</th>
                        <th>Requested Role</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $status = $user->staffProfile?->employment_status ?? 'active';
                            $requestedRole = $user->staffProfile?->requested_role;
                            $userRoleNames = $user->roles->pluck('name')->all();
                        @endphp
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->branch?->name ?: '-' }}</td>
                            <td>{{ $user->department?->name ?: '-' }}</td>
                            <td>{{ $requestedRole ?: '-' }}</td>
                            <td>{{ $user->roles->pluck('name')->join(', ') ?: '-' }}</td>
                            <td><span class="kfms-status is-{{ $status }}">{{ ucfirst($status) }}</span></td>
                            <td>
                                <div class="kfms-table-actions">
                                    @if ($status === 'pending')
                                        <a href="{{ route('access.approvals.show', $user->staffProfile) }}">Review</a>
                                    @endif
                                    @can('access.users.edit')
                                        <a href="{{ route('access.users.edit', $user) }}">Edit</a>
                                    @endcan
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('access.users.destroy', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="is-danger" type="submit" onclick="return confirm('Delete this user?')">Delete</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kfms-empty">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </section>
@endsection
