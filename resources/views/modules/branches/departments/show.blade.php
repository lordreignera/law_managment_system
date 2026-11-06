@extends('layouts.admin')

@section('title', $department->name)
@section('page-title', 'Branches')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $department->name }}</h2>
                <span>{{ $department->code }} · {{ $branch->name }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('branches.show', $branch) }}"><i class="mdi mdi-arrow-left"></i> Back to Branch</a>
                <a class="kfms-btn" href="{{ route('departments.edit', [$branch, $department]) }}"><i class="mdi mdi-pencil-outline"></i> Edit Department</a>
            </div>
        </div>

        <div class="kfms-detail-grid">
            <div><span>Department</span><strong>{{ $department->name }}</strong></div>
            <div><span>Code</span><strong>{{ $department->code }}</strong></div>
            <div><span>Branch</span><strong>{{ $branch->name }}</strong></div>
            <div><span>Status</span><strong><span class="kfms-status {{ $department->is_active ? 'is-active' : 'is-muted' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></strong></div>
            <div class="kfms-span-2"><span>Description</span><strong>{{ $department->description ?: '-' }}</strong></div>
        </div>

        <div class="kfms-section-heading">
            <h3>Assigned Staff</h3>
            <span>{{ $department->users->count() }} staff member{{ $department->users->count() === 1 ? '' : 's' }} assigned to this department.</span>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($department->users->sortBy('name') as $user)
                        @php($status = $user->staffProfile?->employment_status ?? 'active')
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->roles->pluck('name')->join(', ') ?: '-' }}</td>
                            <td><span class="kfms-status is-{{ $status }}">{{ ucfirst($status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="kfms-empty">No staff members are assigned to this department.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
