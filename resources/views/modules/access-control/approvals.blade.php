@extends('layouts.admin')

@section('title', 'Approval Requests')
@section('page-title', 'Access Control')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Approval Requests</h2>
                <span>Pending staff access requests from registration.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('access.users.index') }}">
                <i class="mdi mdi-account-group-outline"></i>
                All Users
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('access.approvals.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search pending request">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('access.approvals.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Job Title</th>
                        <th>Branch</th>
                        <th>Requested Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profiles as $profile)
                        <tr>
                            <td>{{ $profile->user?->name }}</td>
                            <td>{{ $profile->user?->email }}</td>
                            <td>{{ $profile->phone ?: '-' }}</td>
                            <td>{{ $profile->job_title ?: '-' }}</td>
                            <td>{{ $profile->branch?->name ?: '-' }}</td>
                            <td>{{ $profile->requested_role ?: '-' }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#review-request-{{ $profile->id }}">Review</button>
                                    <form method="POST" action="{{ route('access.users.destroy', $profile->user) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="is-danger" type="submit" onclick="return confirm('Delete this access request?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        @push('modals')
                            <div class="modal fade kfms-modal" id="review-request-{{ $profile->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <div>
                                                <h5 class="modal-title">Review Access Request</h5>
                                                <span>{{ $profile->user?->email }}</span>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form class="kfms-form" method="POST" action="{{ route('access.users.approve', $profile->user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-body">
                                                <div class="kfms-review-summary">
                                                    <div>
                                                        <span>Name</span>
                                                        <strong>{{ $profile->user?->name ?: '-' }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>Email</span>
                                                        <strong>{{ $profile->user?->email ?: '-' }}</strong>
                                                    </div>
                                                </div>

                                                <div class="kfms-form-grid">
                                                    <label>
                                                        <span>Phone</span>
                                                        <input type="text" name="phone" value="{{ old('phone', $profile->phone) }}" required>
                                                    </label>
                                                    <label>
                                                        <span>Job Title</span>
                                                        <input type="text" name="job_title" value="{{ old('job_title', $profile->job_title) }}" required>
                                                    </label>
                                                    <label>
                                                        <span>Branch</span>
                                                        <select name="branch_id" required>
                                                            @foreach ($branches as $branch)
                                                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $profile->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <span>Department</span>
                                                        <select name="department_id" required>
                                                            @foreach ($departments as $department)
                                                                <option value="{{ $department->id }}" @selected((string) old('department_id', $profile->department_id) === (string) $department->id)>{{ $department->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="kfms-span-2">
                                                        <span>Approved Role</span>
                                                        <select name="requested_role" required>
                                                            @foreach ($roles as $role)
                                                                <option value="{{ $role->name }}" @selected(old('requested_role', $profile->requested_role) === $role->name)>{{ $role->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                </div>

                                                <div class="kfms-form-actions">
                                                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit">Approve Reviewed Request</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endpush
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No pending access requests.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $profiles->links() }}
    </section>
@endsection
