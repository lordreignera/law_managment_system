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
            <a class="kfms-link-btn" href="{{ route('access.approvals.index') }}">
                <i class="mdi mdi-account-clock-outline"></i>
                Approval Requests
            </a>
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
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#edit-user-{{ $user->id }}">Edit</button>
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

                        @push('modals')
                            <div class="modal fade kfms-modal" id="edit-user-{{ $user->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title">Edit User Access</h5>
                                                <span>{{ $user->email }}</span>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form class="kfms-form" method="POST" action="{{ route('access.users.update', $user) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-body">
                                                @php
                                                    $userDirectPermissionNames = $user->permissions->pluck('name')->all();
                                                @endphp
                                                <div class="kfms-form-grid">
                                                    <label>
                                                        <span>Status</span>
                                                        <select name="employment_status" required>
                                                            @foreach (['pending', 'active', 'inactive', 'suspended'] as $option)
                                                                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>Requested Role</span>
                                                        <input type="text" value="{{ $requestedRole ?: 'None' }}" disabled>
                                                    </label>
                                                    <label class="kfms-span-2">
                                                        <span>Assigned Roles</span>
                                                        <select name="roles[]" multiple>
                                                            @foreach ($roles as $role)
                                                                <option value="{{ $role->name }}" @selected(in_array($role->name, $userRoleNames, true))>{{ $role->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                </div>

                                                <div class="kfms-form-section-title">Direct Permissions</div>
                                                <p class="kfms-muted-text" style="margin: 0 0 10px;">Grants on top of the user's roles. Useful for one-off access without creating a new role.</p>
                                                <div class="kfms-check-grid kfms-permission-grid">
                                                    @foreach ($permissionGroups as $label => $groupPermissions)
                                                        <details class="kfms-permission-group" @if (collect($groupPermissions)->pluck('name')->intersect($userDirectPermissionNames)->isNotEmpty()) open @endif>
                                                            <summary>{{ $label }} <span>({{ count($groupPermissions) }})</span></summary>
                                                            <div class="kfms-permission-options">
                                                                @foreach ($groupPermissions as $permission)
                                                                    <label>
                                                                        <input type="checkbox" name="direct_permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $userDirectPermissionNames, true))>
                                                                        <span>{{ $permission->name }}</span>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        </details>
                                                    @endforeach
                                                </div>

                                                <div class="kfms-form-actions">
                                                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit">Save Access</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endpush
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
