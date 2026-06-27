<div class="modal fade kfms-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ $permission->exists ? 'Edit Permission' : 'Add Permission' }}</h5>
                    <span>{{ $permission->exists ? $permission->guard_name : 'Create a permission key for roles.' }}</span>
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
                            <span>Permission Name</span>
                            <input type="text" name="name" value="{{ old('name', $permission->name) }}" placeholder="e.g. manage access control" required>
                        </label>
                    </div>
                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit">Save Permission</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
