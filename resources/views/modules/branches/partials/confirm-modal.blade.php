<div class="modal fade kfms-modal kfms-confirm-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="{{ $modalId }}-label">{{ $title }}</h5>
                        <span>{{ $subtitle }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="kfms-confirm-icon"><i class="mdi mdi-alert-outline" aria-hidden="true"></i></div>
                    <p class="kfms-confirm-message">{{ $message }}</p>
                </div>
                <div class="modal-footer">
                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="kfms-btn kfms-btn-danger" type="submit">
                        <i class="mdi mdi-delete-outline"></i>
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
