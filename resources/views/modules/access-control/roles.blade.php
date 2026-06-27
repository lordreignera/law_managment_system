@extends('layouts.admin')

@section('title', 'Roles')
@section('page-title', 'Access Control')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Roles</h2>
                <span>Create roles and attach permissions to each role.</span>
            </div>
            <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#create-role-modal">
                <i class="mdi mdi-plus"></i>
                Add Role
            </button>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('access.roles.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search role">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('access.roles.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td>
                                <div class="kfms-chip-list">
                                    @foreach ($role->permissions->take(6) as $permission)
                                        <span>{{ $permission->name }}</span>
                                    @endforeach
                                    @if ($role->permissions->count() > 6)
                                        <span>+{{ $role->permissions->count() - 6 }} more</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ \App\Models\User::role($role->name)->count() }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#edit-role-{{ $role->id }}">Edit</button>
                                    @unless (in_array($role->name, ['Super Admin', 'Administrator'], true))
                                        <form method="POST" action="{{ route('access.roles.destroy', $role) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="is-danger" type="submit" onclick="return confirm('Delete this role?')">Delete</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>

                        @push('modals')
                            @include('modules.access-control.partials.role-modal', ['modalId' => 'edit-role-'.$role->id, 'role' => $role, 'permissions' => $permissions, 'action' => route('access.roles.update', $role), 'method' => 'PUT'])
                        @endpush
                    @empty
                        <tr>
                            <td colspan="4" class="kfms-empty">No roles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $roles->links() }}
    </section>

    @push('modals')
        @include('modules.access-control.partials.role-modal', ['modalId' => 'create-role-modal', 'role' => new \Spatie\Permission\Models\Role, 'permissions' => $permissions, 'action' => route('access.roles.store'), 'method' => null])
    @endpush
@endsection
