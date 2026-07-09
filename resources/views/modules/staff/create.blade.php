@extends('layouts.admin')

@section('title', 'Register Staff')
@section('page-title', 'Register Staff')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>New Staff Member</h2>
                <span>Create login access and HR profile details</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('staff.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Staff
            </a>
        </div>

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">
                Please correct the highlighted fields and try again.
            </div>
        @endif

        <form class="kfms-form-grid" method="POST" action="{{ route('staff.store') }}">
            @csrf

            <label>
                <span>Full Name *</span>
                <input type="text" name="name" value="{{ old('name') }}" required>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Email Address *</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Temporary Password *</span>
                <input type="password" name="password" required autocomplete="new-password">
                @error('password')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Confirm Password *</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <label>
                <span>Staff Number</span>
                <input type="text" name="staff_no" value="{{ old('staff_no') }}">
                @error('staff_no')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Phone *</span>
                <input type="text" name="phone" value="{{ old('phone') }}" required>
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Job Title *</span>
                <input type="text" name="job_title" value="{{ old('job_title') }}" required>
                @error('job_title')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>System Role *</span>
                <select name="role" required>
                    <option value="">Select role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Branch *</span>
                <select name="branch_id" required>
                    <option value="">Select branch</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Department *</span>
                <select name="department_id" required>
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Joined On</span>
                <input type="date" name="joined_on" value="{{ old('joined_on', now()->toDateString()) }}">
                @error('joined_on')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Employment Status *</span>
                <select name="employment_status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('employment_status')<small>{{ $message }}</small>@enderror
            </label>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('staff.index') }}">Cancel</a>
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-account-plus-outline"></i>
                    Register Staff
                </button>
            </div>
        </form>
    </section>
@endsection
