@extends('layouts.admin')

@section('title', 'Edit Staff Profile')
@section('page-title', 'Edit Staff Profile')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $staff->name }}</h2>
                <span>Update HR profile details</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('staff.show', $staff) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Profile
            </a>
        </div>

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">
                Please correct the highlighted fields and try again.
            </div>
        @endif

        <form class="kfms-form-grid" method="POST" action="{{ route('staff.update', $staff) }}">
            @csrf
            @method('PUT')

            <label>
                <span>Full Name *</span>
                <input type="text" name="name" value="{{ old('name', $staff->name) }}" required>
                @error('name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Email Address *</span>
                <input type="email" name="email" value="{{ old('email', $staff->email) }}" required>
                @error('email')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Staff Number</span>
                <input type="text" name="staff_no" value="{{ old('staff_no', $staff->staffProfile?->staff_no) }}">
                @error('staff_no')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Phone</span>
                <input type="text" name="phone" value="{{ old('phone', $staff->staffProfile?->phone) }}">
                @error('phone')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Job Title</span>
                <input type="text" name="job_title" value="{{ old('job_title', $staff->staffProfile?->job_title) }}">
                @error('job_title')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Joined On</span>
                <input type="date" name="joined_on" value="{{ old('joined_on', $staff->staffProfile?->joined_on?->format('Y-m-d')) }}">
                @error('joined_on')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Branch</span>
                <select name="branch_id">
                    <option value="">Select branch</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $staff->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Department</span>
                <select name="department_id">
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id', $staff->department_id) === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Employment Status *</span>
                <select name="employment_status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_status', $staff->staffProfile?->employment_status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('employment_status')<small>{{ $message }}</small>@enderror
            </label>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('staff.show', $staff) }}">Cancel</a>
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save-outline"></i>
                    Save Profile
                </button>
            </div>
        </form>
    </section>
@endsection
