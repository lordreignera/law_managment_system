@extends('layouts.admin')

@section('title', 'Branches')
@section('page-title', 'Branches')

@section('content')
    <section class="kfms-panel kfms-branches-page">
        <div class="kfms-panel-header">
            <div>
                <h2>Branches</h2>
                <span>{{ $branches->total() }} branch{{ $branches->total() === 1 ? '' : 'es' }} · manage branch departments in one workspace</span>
            </div>
            @can('branches.create')
                <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#create-branch-modal">
                    <i class="mdi mdi-plus"></i>
                    Add Branch
                </button>
            @endcan
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('branches.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, code or city">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply</button>
                <a class="kfms-link-btn" href="{{ route('branches.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr><th>Branch</th><th>City</th><th>Staff</th><th>Departments</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td><strong>{{ $branch->name }}</strong><small class="kfms-table-subtext">{{ $branch->code }}</small></td>
                            <td>{{ $branch->city ?: '-' }}</td>
                            <td>{{ number_format($branch->users_count) }}</td>
                            <td><span class="kfms-count-pill">{{ number_format($branch->departments_count) }}</span></td>
                            <td><span class="kfms-status {{ $branch->is_active ? 'is-active' : 'is-muted' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#branch-view-{{ $branch->id }}"><i class="mdi mdi-eye-outline"></i> View</button>
                                    @can('branches.update')
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#branch-edit-{{ $branch->id }}"><i class="mdi mdi-pencil-outline"></i> Edit</button>
                                        <button class="is-danger" type="button" data-bs-toggle="modal" data-bs-target="#branch-delete-{{ $branch->id }}"><i class="mdi mdi-delete-outline"></i> Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="kfms-empty">No branches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $branches->links() }}
    </section>

    @can('branches.create')
        @include('modules.branches.partials.branch-form-modal', [
            'modalId' => 'create-branch-modal',
            'branch' => null,
            'code' => $createCode,
            'action' => route('branches.store'),
            'method' => null,
            'title' => 'Add Branch',
            'subtitle' => 'Create a new office branch.',
        ])
    @endcan

    @foreach ($branches as $branch)
        @include('modules.branches.partials.branch-view-modal', ['branch' => $branch])

        @can('branches.update')
            @include('modules.branches.partials.branch-form-modal', [
                'modalId' => 'branch-edit-'.$branch->id,
                'branch' => $branch,
                'code' => null,
                'action' => route('branches.update', $branch),
                'method' => 'PUT',
                'title' => 'Edit Branch',
                'subtitle' => 'Update '.$branch->name.' details.',
            ])
            @include('modules.branches.partials.confirm-modal', [
                'modalId' => 'branch-delete-'.$branch->id,
                'action' => route('branches.destroy', $branch),
                'title' => 'Delete Branch',
                'subtitle' => $branch->name,
                'message' => 'This branch can only be deleted when it has no staff or departments.',
            ])
            @include('modules.branches.partials.department-form-modal', [
                'modalId' => 'department-create-'.$branch->id,
                'branch' => $branch,
                'department' => new \App\Models\Department(['is_active' => true]),
                'action' => route('departments.store', $branch),
                'method' => null,
                'title' => 'Add Department',
                'subtitle' => 'Add a department under '.$branch->name.'.',
            ])
        @endcan

        @foreach ($branch->departments as $department)
            @include('modules.branches.partials.department-view-modal', ['branch' => $branch, 'department' => $department, 'modalId' => 'department-view-'.$branch->id.'-'.$department->id])
            @can('branches.update')
                @include('modules.branches.partials.department-form-modal', [
                    'modalId' => 'department-edit-'.$branch->id.'-'.$department->id,
                    'branch' => $branch,
                    'department' => $department,
                    'action' => route('departments.update', [$branch, $department]),
                    'method' => 'PUT',
                    'title' => 'Edit Department',
                    'subtitle' => $department->name.' · '.$branch->name,
                ])
                @include('modules.branches.partials.confirm-modal', [
                    'modalId' => 'department-delete-'.$branch->id.'-'.$department->id,
                    'action' => route('departments.destroy', [$branch, $department]),
                    'title' => 'Delete Department',
                    'subtitle' => $department->name,
                    'message' => 'Staff assigned to this department must be reassigned before it can be deleted.',
                ])
            @endcan
        @endforeach
    @endforeach
@endsection
