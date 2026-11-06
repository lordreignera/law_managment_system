<div class="modal fade kfms-modal kfms-department-view-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $modalId }}-label">{{ $department->name }}</h5>
                    <span>{{ $department->code }} · {{ $branch->name }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="kfms-branch-modal-details">
                    <div><span>Status</span><strong>{{ $department->is_active ? 'Active' : 'Inactive' }}</strong></div>
                    <div><span>Staff</span><strong>{{ number_format($department->users_count ?? $department->users()->count()) }}</strong></div>
                    <div><span>Sort order</span><strong>{{ $department->sort_order ?? 0 }}</strong></div>
                </div>
                <div class="kfms-modal-description">
                    <span>Description</span>
                    <p>{{ $department->description ?: 'No description has been added.' }}</p>
                </div>
            </div>
            <div class="modal-footer">
                @can('branches.update')
                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#department-edit-{{ $branch->id }}-{{ $department->id }}">
                        <i class="mdi mdi-pencil-outline"></i> Edit
                    </button>
                @endcan
                <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
