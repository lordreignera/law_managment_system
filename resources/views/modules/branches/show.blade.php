@extends('layouts.admin')

@section('title', $branch->name)
@section('page-title', 'Branches')

@section('content')
    <section class="kfms-panel kfms-branch-detail">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $branch->name }}</h2>
                <span>{{ $branch->code }}{{ $branch->city ? ' · '.$branch->city : '' }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('branches.index') }}"><i class="mdi mdi-arrow-left"></i> Back to Branches</a>
                @can('branches.update')
                    <button class="kfms-link-btn" type="button" data-bs-toggle="modal" data-bs-target="#branch-edit-{{ $branch->id }}"><i class="mdi mdi-pencil-outline"></i> Edit Branch</button>
                    <button class="kfms-btn kfms-btn-compact" type="button" data-bs-toggle="modal" data-bs-target="#department-create-{{ $branch->id }}"><i class="mdi mdi-plus"></i> Add Department</button>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-branch-summary">
            <div><span class="kfms-card-label">Status</span><strong>{{ $branch->is_active ? 'Active' : 'Inactive' }}</strong></div>
            <div><span class="kfms-card-label">Staff</span><strong>{{ number_format($staffCount) }}</strong></div>
            <div><span class="kfms-card-label">Departments</span><strong>{{ number_format($branch->departments_count) }}</strong></div>
            <div><span class="kfms-card-label">Address</span><strong>{{ $branch->address ?: '-' }}</strong></div>
        </div>

        <div class="kfms-section-heading">
            <div><h3>Departments</h3><span>View or manage departments without leaving this page.</span></div>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Code</th><th>Department</th><th>Staff</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($branch->departments as $department)
                        <tr>
                            <td>{{ $department->code }}</td>
                            <td><strong>{{ $department->name }}</strong><small class="kfms-table-subtext">{{ $department->description ?: 'No description' }}</small></td>
                            <td>{{ number_format($department->users_count ?? 0) }}</td>
                            <td><span class="kfms-status {{ $department->is_active ? 'is-active' : 'is-muted' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#department-view-{{ $branch->id }}-{{ $department->id }}"><i class="mdi mdi-eye-outline"></i> View</button>
                                    @can('branches.update')
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#department-edit-{{ $branch->id }}-{{ $department->id }}"><i class="mdi mdi-pencil-outline"></i> Edit</button>
                                        <button class="is-danger" type="button" data-bs-toggle="modal" data-bs-target="#department-delete-{{ $branch->id }}-{{ $department->id }}"><i class="mdi mdi-delete-outline"></i> Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="kfms-empty">No departments have been added to this branch yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

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
@endsection
