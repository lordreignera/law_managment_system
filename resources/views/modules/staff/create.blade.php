@extends('layouts.admin')

@php
    $formContext = $formContext ?? [
        'title' => 'Register Staff',
        'heading' => 'New Staff Member',
        'subtitle' => 'Create login access and HR profile details',
        'backUrl' => route('staff.index'),
        'backLabel' => 'Back to Staff',
        'action' => route('staff.store'),
        'submitLabel' => 'Register Staff',
    ];
@endphp

@section('title', $formContext['title'])
@section('page-title', $formContext['title'])

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $formContext['heading'] }}</h2>
                <span>{{ $formContext['subtitle'] }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ $formContext['backUrl'] }}">
                <i class="mdi mdi-arrow-left"></i>
                {{ $formContext['backLabel'] }}
            </a>
        </div>

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">
                Please correct the highlighted fields and try again.
            </div>
        @endif

        <form class="kfms-form kfms-staff-create-form" method="POST" action="{{ $formContext['action'] }}">
            @csrf

            <div class="kfms-staff-create-banner">
                <span class="kfms-status is-active">Approved Access</span>
                <p>Staff created here are activated immediately, email verified, and routed by the selected system role.</p>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Login Access</div>
                <div class="kfms-form-grid">
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
                        <div class="kfms-password-field">
                            <input id="staff-create-password" type="password" name="password" required autocomplete="new-password">
                            <button type="button" data-password-toggle="staff-create-password" aria-label="Show temporary password" title="Show password">
                                <i class="mdi mdi-eye-outline"></i>
                            </button>
                        </div>
                        @error('password')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Confirm Password *</span>
                        <div class="kfms-password-field">
                            <input id="staff-create-password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                            <button type="button" data-password-toggle="staff-create-password-confirmation" aria-label="Show password confirmation" title="Show password">
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
                        <small>Generated automatically when the staff record is saved.</small>
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
                        <span>Joined On</span>
                        <input type="date" name="joined_on" value="{{ old('joined_on', now()->toDateString()) }}">
                        @error('joined_on')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Role & Workspace</div>
                <div class="kfms-form-grid">
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

                    <div class="kfms-staff-auto-approval">
                        <span class="kfms-card-label">Employment Status</span>
                        <strong>Active</strong>
                    </div>
                </div>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ $formContext['backUrl'] }}">Cancel</a>
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-account-plus-outline"></i>
                    {{ $formContext['submitLabel'] }}
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
