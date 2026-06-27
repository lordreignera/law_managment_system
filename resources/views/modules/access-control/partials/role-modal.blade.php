<div class="modal fade kfms-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ $role->exists ? 'Edit Role' : 'Add Role' }}</h5>
                    <span>{{ $role->exists ? $role->name : 'Create a new role and attach permissions.' }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="kfms-form" method="POST" action="{{ $action }}">
                @csrf
                @if ($method)
                    @method($method)
                @endif
                <div class="modal-body">
                    @php
                        $assignedPermissionNames = $role->exists ? $role->permissions->pluck('name')->all() : [];
                    @endphp
                    <div class="kfms-form-grid">
                        <label class="kfms-span-2">
                            <span>Role Name</span>
                            <input type="text" name="name" value="{{ old('name', $role->name) }}" required>
                        </label>
                    </div>

                    <div class="kfms-form-section-title">Permissions</div>
                    <p class="kfms-muted-text" style="margin: 0 0 10px;">Permissions are derived from your application routes. Tick whole modules or pick individual actions.</p>
                    <div class="kfms-check-grid kfms-permission-grid">
                        @foreach ($permissionGroups as $label => $groupPermissions)
                            @php
                                $groupNames = collect($groupPermissions)->pluck('name')->all();
                                $hasSelected = count(array_intersect($groupNames, $assignedPermissionNames)) > 0;
                            @endphp
                            <details class="kfms-permission-group" @if ($hasSelected || ! $role->exists) open @endif>
                                <summary>{{ $label }} <span>({{ count($groupPermissions) }})</span></summary>
                                <div class="kfms-permission-options">
                                    @foreach ($groupPermissions as $permission)
                                        <label>
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $assignedPermissionNames, true))>
                                            <span>{{ $permission->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit">Save Role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
