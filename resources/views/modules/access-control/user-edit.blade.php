@extends('layouts.admin')

@section('title', 'Edit User')
@section('page-title', 'Access Control')

@section('content')
    @php
        $status = old('employment_status', $user->staffProfile?->employment_status ?? 'active');
        $selectedRoles = old('roles', $user->roles->pluck('name')->all());
        $selectedPermissions = old('direct_permissions', $user->permissions->pluck('name')->all());
        $selectedRoles = is_array($selectedRoles) ? $selectedRoles : [];
        $selectedPermissions = is_array($selectedPermissions) ? $selectedPermissions : [];
        $requestedRole = $user->staffProfile?->requested_role;
    @endphp

    <section class="kfms-panel kfms-access-user-editor">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit User Access</h2>
                <span>{{ $user->email }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('access.users.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Users
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">
                Please correct the highlighted fields and try again.
            </div>
        @endif

        <form class="kfms-form kfms-access-user-form" method="POST" action="{{ route('access.users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="kfms-access-user-summary">
                <div>
                    <span class="kfms-card-label">Current Status</span>
                    <strong><span class="kfms-status is-{{ $status }}">{{ ucfirst($status) }}</span></strong>
                </div>
                <div>
                    <span class="kfms-card-label">Requested Role</span>
                    <strong>{{ $requestedRole ?: 'None' }}</strong>
                </div>
                <div>
                    <span class="kfms-card-label">Assigned Roles</span>
                    <strong data-selection-summary="roles">{{ count($selectedRoles) }} selected</strong>
                </div>
                <div>
                    <span class="kfms-card-label">Direct Permissions</span>
                    <strong data-selection-summary="direct_permissions">{{ count($selectedPermissions) }} selected</strong>
                </div>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Account Details</div>
                <div class="kfms-form-grid">
                    <label>
                        <span>Full Name *</span>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Email Address *</span>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>New Password</span>
                        <div class="kfms-password-field">
                            <input id="access-user-password" type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                            <button type="button" data-password-toggle="access-user-password" aria-label="Show new password" title="Show password">
                                <i class="mdi mdi-eye-outline"></i>
                            </button>
                        </div>
                        @error('password')<small>{{ $message }}</small>@enderror
                    </label>

                    <label>
                        <span>Confirm New Password</span>
                        <div class="kfms-password-field">
                            <input id="access-user-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                            <button type="button" data-password-toggle="access-user-password-confirmation" aria-label="Show password confirmation" title="Show password">
                                <i class="mdi mdi-eye-outline"></i>
                            </button>
                        </div>
                    </label>
                </div>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Access Status & Roles</div>
                <div class="kfms-form-grid">
                    <label>
                        <span>Status *</span>
                        <select name="employment_status" required>
                            @foreach ($statuses as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                        @error('employment_status')<small>{{ $message }}</small>@enderror
                    </label>

                    <div class="kfms-multi-dropdown" data-selection-group="roles">
                        <span class="kfms-field-label">Assigned Roles</span>
                        <details>
                            <summary>
                                <span data-selection-summary="roles">{{ count($selectedRoles) }} selected</span>
                                <i class="mdi mdi-chevron-down"></i>
                            </summary>
                            <div class="kfms-multi-dropdown-panel">
                                @foreach ($roles as $role)
                                    <label class="kfms-tick-option">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles, true))>
                                        <span class="kfms-tick-mark"><i class="mdi mdi-check"></i></span>
                                        <span class="kfms-tick-text">{{ $role->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                        @error('roles')<small>{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            <div class="kfms-staff-form-section">
                <div class="kfms-form-section-title">Direct Permissions</div>
                <div class="kfms-access-permission-grid" data-selection-group="direct_permissions">
                    @foreach ($permissionGroups as $label => $groupPermissions)
                        @php
                            $groupSelectedCount = collect($groupPermissions)->pluck('name')->intersect($selectedPermissions)->count();
                        @endphp
                        <details class="kfms-permission-group kfms-access-permission-group" data-permission-group @if ($groupSelectedCount > 0) open @endif>
                            <summary>
                                {{ $label }}
                                <span data-permission-group-summary>{{ $groupSelectedCount }} / {{ count($groupPermissions) }} selected</span>
                            </summary>
                            <div class="kfms-permission-panel">
                                <div class="kfms-permission-toolbar">
                                    <button type="button" data-permission-select="all">
                                        <i class="mdi mdi-check-all"></i>
                                        Select all
                                    </button>
                                    <button type="button" data-permission-select="none">
                                        <i class="mdi mdi-close"></i>
                                        Clear
                                    </button>
                                </div>
                                <div class="kfms-permission-options">
                                    @foreach ($groupPermissions as $permission)
                                        <label class="kfms-tick-option kfms-permission-tick">
                                            <input type="checkbox" name="direct_permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $selectedPermissions, true))>
                                            <span class="kfms-tick-mark"><i class="mdi mdi-check"></i></span>
                                            <span class="kfms-tick-text">{{ $permission->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
                @error('direct_permissions')<small>{{ $message }}</small>@enderror
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('access.users.index') }}">Cancel</a>
                @can('access.users.update')
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-content-save-outline"></i>
                        Save User Access
                    </button>
                @endcan
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

        document.querySelectorAll('[data-selection-group]').forEach(function (group) {
            const name = group.dataset.selectionGroup;
            const update = function () {
                const selected = document.querySelectorAll('input[name="' + name + '[]"]:checked').length;
                document.querySelectorAll('[data-selection-summary="' + name + '"]').forEach(function (summary) {
                    summary.textContent = selected + ' selected';
                });
            };

            group.addEventListener('change', update);
            update();
        });

        document.querySelectorAll('[data-permission-group]').forEach(function (group) {
            const update = function () {
                const checked = group.querySelectorAll('input[type="checkbox"]:checked').length;
                const total = group.querySelectorAll('input[type="checkbox"]').length;
                const summary = group.querySelector('[data-permission-group-summary]');

                if (summary) {
                    summary.textContent = checked + ' / ' + total + ' selected';
                }
            };

            group.addEventListener('change', update);
            group.addEventListener('click', function (event) {
                const action = event.target.closest('[data-permission-select]')?.dataset.permissionSelect;

                if (! action) {
                    return;
                }

                group.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                    input.checked = action === 'all';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
            update();
        });
    </script>
@endpush
