@php
    $assignedPermissions = $role->permissions->keyBy('name');
    $assignedGroups = collect($permissionGroups)
        ->map(fn ($group) => collect($group)->filter(fn ($permission) => $assignedPermissions->has($permission->name)))
        ->filter(fn ($group) => $group->isNotEmpty());
@endphp

<div class="modal fade kfms-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog kfms-role-view-modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $modalId }}-label">{{ $role->name }}</h5>
                    <span>Role access overview</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="kfms-access-role-summary">
                    <div>
                        <span class="kfms-card-label">Assigned Permissions</span>
                        <strong>{{ $role->permissions->count() }}</strong>
                    </div>
                    <div>
                        <span class="kfms-card-label">Assigned Users</span>
                        <strong>{{ \App\Models\User::role($role->name)->count() }}</strong>
                    </div>
                </div>

                <div class="kfms-form-section-title">Permissions by module</div>
                @if ($assignedGroups->isNotEmpty())
                    <div class="kfms-permission-grid kfms-role-permission-view">
                        @foreach ($assignedGroups as $label => $groupPermissions)
                            <details class="kfms-permission-group" open>
                                <summary>
                                    {{ $label }}
                                    <span>{{ $groupPermissions->count() }} {{ str('permission')->plural($groupPermissions->count()) }}</span>
                                </summary>
                                <div class="kfms-permission-options">
                                    @foreach ($groupPermissions as $permission)
                                        <div class="kfms-readonly-permission">
                                            <i class="mdi mdi-check-circle-outline" aria-hidden="true"></i>
                                            <span>{{ $permission->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                @else
                    <div class="kfms-empty kfms-role-empty-state">This role has no permissions assigned.</div>
                @endif
            </div>

            <div class="modal-footer">
                <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
