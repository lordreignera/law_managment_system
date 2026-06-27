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
                    <div class="kfms-form-grid">
                        <label>
                            <span>Role Name</span>
                            <input type="text" name="name" value="{{ old('name', $role->name) }}" required>
                        </label>
                        <label class="kfms-span-2">
                            <span>Permissions</span>
                            <div class="kfms-check-grid">
                                @foreach ($permissions as $permission)
                                    <label>
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked($role->exists && $role->permissions->contains('name', $permission->name))>
                                        <span>{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </label>
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
