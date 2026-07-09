@extends('layouts.admin')

@section('title', 'Review Access Request')
@section('page-title', 'Access Control')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Review Access Request</h2>
                <span>{{ $profile->user?->name }} requested access to the firm workspace.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('access.approvals.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Requests
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Name</span>
                <strong>{{ $profile->user?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Email</span>
                <strong>{{ $profile->user?->email ?: '-' }}</strong>
            </div>
            <div>
                <span>Phone</span>
                <strong>{{ $profile->phone ?: '-' }}</strong>
            </div>
            <div>
                <span>Current Status</span>
                <strong>{{ ucfirst($profile->employment_status) }}</strong>
            </div>
            <div>
                <span>Requested Role</span>
                <strong>{{ $profile->requested_role ?: '-' }}</strong>
            </div>
            <div>
                <span>Job Title</span>
                <strong>{{ $profile->job_title ?: '-' }}</strong>
            </div>
            <div>
                <span>Requested Branch</span>
                <strong>{{ $profile->branch?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Requested Department</span>
                <strong>{{ $profile->department?->name ?: '-' }}</strong>
            </div>
        </div>

        <div class="kfms-section-heading">
            <h3>Approve Access</h3>
            <span>Confirm the final branch, department, and role before activating this user.</span>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('access.users.approve', $profile->user) }}">
            @csrf
            @method('PATCH')

            <div class="kfms-form-grid">
                <label>
                    <span>Phone <span class="kfms-required">*</span></span>
                    <input type="text" name="phone" value="{{ old('phone', $profile->phone) }}" required>
                    @error('phone') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Job Title <span class="kfms-required">*</span></span>
                    <input type="text" name="job_title" value="{{ old('job_title', $profile->job_title) }}" required>
                    @error('job_title') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Branch <span class="kfms-required">*</span></span>
                    <select name="branch_id" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $profile->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Department <span class="kfms-required">*</span></span>
                    <select name="department_id" required>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) old('department_id', $profile->department_id) === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="kfms-span-2">
                    <span>Approved Role <span class="kfms-required">*</span></span>
                    <select name="requested_role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('requested_role', $profile->requested_role) === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('requested_role') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('access.approvals.index') }}">Cancel</a>
                <button type="submit">
                    <i class="mdi mdi-account-check-outline"></i>
                    Approve User
                </button>
            </div>
        </form>

        <div class="kfms-section-heading">
            <h3>Reject Request</h3>
            <span>Rejecting removes the pending account request from the system.</span>
        </div>

        <form class="kfms-form-actions" method="POST" action="{{ route('access.users.destroy', $profile->user) }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="redirect_to" value="approvals">
            <button class="kfms-link-btn kfms-danger" type="submit" onclick="return confirm('Reject and delete this access request?')">
                <i class="mdi mdi-delete-outline"></i>
                Reject Request
            </button>
        </form>
    </section>
@endsection
