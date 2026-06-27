@extends('layouts.admin')

@section('title', 'Permissions')
@section('page-title', 'Access Control')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Permissions</h2>
                <span>Create and maintain permission keys used by roles.</span>
            </div>
            <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#create-permission-modal">
                <i class="mdi mdi-plus"></i>
                Add Permission
            </button>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('access.permissions.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search permission">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('access.permissions.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Guard</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td>{{ $permission->name }}</td>
                            <td>{{ $permission->guard_name }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#edit-permission-{{ $permission->id }}">Edit</button>
                                    <form method="POST" action="{{ route('access.permissions.destroy', $permission) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="is-danger" type="submit" onclick="return confirm('Delete this permission?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        @push('modals')
                            @include('modules.access-control.partials.permission-modal', ['modalId' => 'edit-permission-'.$permission->id, 'permission' => $permission, 'action' => route('access.permissions.update', $permission), 'method' => 'PUT'])
                        @endpush
                    @empty
                        <tr>
                            <td colspan="3" class="kfms-empty">No permissions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $permissions->links() }}
    </section>

    @push('modals')
        @include('modules.access-control.partials.permission-modal', ['modalId' => 'create-permission-modal', 'permission' => new \Spatie\Permission\Models\Permission, 'action' => route('access.permissions.store'), 'method' => null])
    @endpush
@endsection
