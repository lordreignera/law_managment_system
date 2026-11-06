@php
    $isEdit = $branch?->exists;
@endphp

<div class="modal fade kfms-modal kfms-branch-form-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form class="kfms-form" method="POST" action="{{ $action }}">
                @csrf
                @if ($method)
                    @method($method)
                @endif
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="{{ $modalId }}-label">{{ $title }}</h5>
                        <span>{{ $subtitle }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('modules.branches.partials.form')
                </div>
                <div class="modal-footer">
                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-content-save-outline"></i>
                        {{ $isEdit ? 'Save Changes' : 'Save Branch' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
