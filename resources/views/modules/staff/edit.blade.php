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

        <form class="kfms-form kfms-staff-create-form" method="POST" action="{{ route('staff.update', $staff) }}">
            @csrf
            @method('PUT')

            @php
                $currentRole = old('role', $staff->roles->first()?->name);
            @endphp

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Login Access</div>
                <div class="kfms-form-grid">
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
                        <span>New Password</span>
                        <div class="kfms-password-field">
                            <input id="staff-edit-password" type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                            <button type="button" data-password-toggle="staff-edit-password" aria-label="Show new password" title="Show password">
                                <i class="mdi mdi-eye-outline"></i>
                            </button>
                        </div>
                        @error('password')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Confirm New Password</span>
                        <div class="kfms-password-field">
                            <input id="staff-edit-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Repeat new password">
                            <button type="button" data-password-toggle="staff-edit-password-confirmation" aria-label="Show password confirmation" title="Show password">
                                <i class="mdi mdi-eye-outline"></i>
                            </button>
                        </div>
                    </label>
                </div>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Profile Details</div>
                <div class="kfms-form-grid">
                    <label>
                        <span>Staff Number</span>
                        <input type="text" value="{{ $nextStaffNo }}" readonly disabled>
                        <small>{{ $staff->staffProfile?->staff_no ? 'Staff numbers are assigned by the system.' : 'A staff number will be assigned when you save.' }}</small>
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
                </div>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Role & Workspace</div>
                <div class="kfms-form-grid">
                    <label>
                        <span>System Role</span>
                        <select name="role">
                            <option value="">Keep current role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->name }}" @selected($currentRole === $role->name)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('role')<small>{{ $message }}</small>@enderror
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
                </div>
            </div>

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

@push('scripts')
    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-password-toggle]');

            if (! button) {
                return;
            }

            const input = document.getElementById(button.dataset.passwordToggle);
            const icon = button.querySelector('i');

            if (! input) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            button.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
            icon?.classList.toggle('mdi-eye-outline', ! isHidden);
            icon?.classList.toggle('mdi-eye-off-outline', isHidden);
        });
    </script>
@endpush
