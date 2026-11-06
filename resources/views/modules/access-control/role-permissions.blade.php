@extends('layouts.admin')

@section('title', 'Manage Permissions')
@section('page-title', 'Access Control')

@section('content')
    @php
        $assignedPermissionNames = old('permissions', $role->permissions->pluck('name')->all());
        $assignedPermissionNames = is_array($assignedPermissionNames) ? $assignedPermissionNames : [];
    @endphp

    <section class="kfms-panel kfms-role-permission-manager">
        <div class="kfms-panel-header">
            <div>
                <h2>Manage permissions</h2>
                <span>{{ $role->name }} · Role access profile</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('access.roles.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Roles
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">Please correct the highlighted permissions and try again.</div>
        @endif

        <form class="kfms-form" method="POST" action="{{ route('access.roles.permissions.update', $role) }}" data-permission-manager>
            @csrf
            @method('PUT')

            <div class="kfms-role-permission-summary">
                <div>
                    <span class="kfms-card-label">Role</span>
                    <strong>{{ $role->name }}</strong>
                </div>
                <div>
                    <span class="kfms-card-label">Selected permissions</span>
                    <strong data-permissions-selected-count>{{ count($assignedPermissionNames) }}</strong>
                </div>
                <div>
                    <span class="kfms-card-label">Available permissions</span>
                    <strong>{{ collect($permissionGroups)->sum(fn ($group) => count($group)) }}</strong>
                </div>
            </div>

            <div class="kfms-permission-manager-toolbar">
                <label class="kfms-search-box" for="role-permission-search">
                    <i class="mdi mdi-magnify"></i>
                    <input id="role-permission-search" type="search" placeholder="Search permissions" autocomplete="off" data-permission-search>
                </label>
                <div class="kfms-toolbar-actions">
                    <button class="kfms-link-btn" type="button" data-permissions-select-visible>Select all visible</button>
                    <button class="kfms-link-btn" type="button" data-permissions-clear-visible>Clear visible</button>
                </div>
            </div>

            <div class="kfms-role-permission-groups">
                @foreach ($permissionGroups as $label => $groupPermissions)
                    @php
                        $groupSelectedCount = collect($groupPermissions)->pluck('name')->intersect($assignedPermissionNames)->count();
                    @endphp
                    <details class="kfms-role-permission-group" data-permission-group @if ($groupSelectedCount > 0) open @endif>
                        <summary>
                            <span class="kfms-role-permission-group-heading">
                                <strong>{{ $label }}</strong>
                                <small data-group-visible-count>{{ count($groupPermissions) }} permissions</small>
                            </span>
                            <span class="kfms-role-permission-group-status" data-group-selected-count>{{ $groupSelectedCount }} / {{ count($groupPermissions) }} selected</span>
                        </summary>
                        <div class="kfms-role-permission-group-body">
                            <div class="kfms-role-permission-group-toolbar">
                                <button type="button" data-group-select="all">
                                    <i class="mdi mdi-check-all"></i>
                                    Select all
                                </button>
                                <button type="button" data-group-select="none">
                                    <i class="mdi mdi-close"></i>
                                    Clear
                                </button>
                            </div>
                            <div class="kfms-role-permission-options">
                                @foreach ($groupPermissions as $permission)
                                    <label class="kfms-role-permission-option" data-permission-option data-permission-name="{{ strtolower($permission->name) }}">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $assignedPermissionNames, true))>
                                        <span class="kfms-role-permission-check"><i class="mdi mdi-check"></i></span>
                                        <span class="kfms-role-permission-name">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="kfms-role-permission-no-results" data-permission-no-results hidden>No permissions match your search in this group.</div>
                        </div>
                    </details>
                @endforeach
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('access.roles.index') }}">Cancel</a>
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save-outline"></i>
                    Save permissions
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const manager = document.querySelector('[data-permission-manager]');

            if (! manager) {
                return;
            }

            const search = manager.querySelector('[data-permission-search]');
            const groups = Array.from(manager.querySelectorAll('[data-permission-group]'));
            const selectedCount = manager.querySelector('[data-permissions-selected-count]');

            const updateGroup = function (group) {
                const options = Array.from(group.querySelectorAll('[data-permission-option]'));
                const visibleOptions = options.filter((option) => ! option.hidden);
                const checked = options.filter((option) => option.querySelector('input')?.checked).length;
                const visibleCount = group.querySelector('[data-group-visible-count]');
                const selectedSummary = group.querySelector('[data-group-selected-count]');
                const noResults = group.querySelector('[data-permission-no-results]');

                if (visibleCount) {
                    visibleCount.textContent = visibleOptions.length === options.length
                        ? options.length + ' permissions'
                        : visibleOptions.length + ' of ' + options.length + ' visible';
                }

                if (selectedSummary) {
                    selectedSummary.textContent = checked + ' / ' + options.length + ' selected';
                }

                if (noResults) {
                    noResults.hidden = visibleOptions.length !== 0;
                }
            };

            const updateAll = function () {
                groups.forEach(updateGroup);
                if (selectedCount) {
                    selectedCount.textContent = manager.querySelectorAll('input[name="permissions[]"]:checked').length;
                }
            };

            search?.addEventListener('input', function () {
                const query = search.value.trim().toLowerCase();

                groups.forEach(function (group) {
                    const options = Array.from(group.querySelectorAll('[data-permission-option]'));
                    const visibleOptions = options.filter(function (option) {
                        const matches = ! query || option.dataset.permissionName.includes(query);
                        option.hidden = ! matches;
                        return matches;
                    });

                    group.hidden = visibleOptions.length === 0;
                    if (visibleOptions.length > 0 && query) {
                        group.open = true;
                    }
                    updateGroup(group);
                });
            });

            manager.addEventListener('change', function (event) {
                if (event.target.matches('input[name="permissions[]"]')) {
                    updateAll();
                }
            });

            manager.addEventListener('click', function (event) {
                const groupButton = event.target.closest('[data-group-select]');
                const globalButton = event.target.closest('[data-permissions-select-visible], [data-permissions-clear-visible]');

                if (groupButton) {
                    event.preventDefault();
                    const group = groupButton.closest('[data-permission-group]');
                    const shouldSelect = groupButton.dataset.groupSelect === 'all';

                    group?.querySelectorAll('[data-permission-option]:not([hidden]) input').forEach(function (input) {
                        input.checked = shouldSelect;
                    });
                    updateAll();
                }

                if (globalButton) {
                    event.preventDefault();
                    const shouldSelect = globalButton.hasAttribute('data-permissions-select-visible');

                    manager.querySelectorAll('[data-permission-option]:not([hidden]) input').forEach(function (input) {
                        input.checked = shouldSelect;
                    });
                    updateAll();
                }
            });

            updateAll();
        });
    </script>
@endpush
