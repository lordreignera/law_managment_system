<div class="modal fade kfms-modal kfms-branch-view-modal" id="branch-view-{{ $branch->id }}" tabindex="-1" aria-labelledby="branch-view-{{ $branch->id }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="branch-view-{{ $branch->id }}-label">{{ $branch->name }}</h5>
                    <span>{{ $branch->code }}{{ $branch->city ? ' · '.$branch->city : '' }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="kfms-branch-summary kfms-branch-summary-modal">
                    <div><span class="kfms-card-label">Status</span><strong>{{ $branch->is_active ? 'Active' : 'Inactive' }}</strong></div>
                    <div><span class="kfms-card-label">Staff</span><strong>{{ number_format($branch->users_count) }}</strong></div>
                    <div><span class="kfms-card-label">Departments</span><strong>{{ number_format($branch->departments_count) }}</strong></div>
                    <div><span class="kfms-card-label">Address</span><strong>{{ $branch->address ?: '-' }}</strong></div>
                </div>

                <div class="kfms-modal-section-heading">
                    <div>
                        <h3>Departments</h3>
                        <span>Departments assigned to {{ $branch->name }}.</span>
                    </div>
                    @can('branches.update')
                        <button class="kfms-btn kfms-btn-compact" type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#department-create-{{ $branch->id }}">
                            <i class="mdi mdi-plus"></i> Add Department
                        </button>
                    @endcan
                </div>

                <div class="kfms-table-wrap">
                    <table class="kfms-table kfms-compact-table">
                        <thead><tr><th>Code</th><th>Department</th><th>Staff</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            @forelse ($branch->departments as $department)
                                <tr>
                                    <td>{{ $department->code }}</td>
                                    <td><strong>{{ $department->name }}</strong></td>
                                    <td>{{ number_format($department->users_count ?? 0) }}</td>
                                    <td><span class="kfms-status {{ $department->is_active ? 'is-active' : 'is-muted' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <div class="kfms-table-actions">
                                            <button type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#department-view-{{ $branch->id }}-{{ $department->id }}">View</button>
                                            @can('branches.update')
                                                <button type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#department-edit-{{ $branch->id }}-{{ $department->id }}">Edit</button>
                                                <button class="is-danger" type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#department-delete-{{ $branch->id }}-{{ $department->id }}">Delete</button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="kfms-empty">No departments have been added to this branch yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                @can('branches.update')
                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#branch-edit-{{ $branch->id }}"><i class="mdi mdi-pencil-outline"></i> Edit Branch</button>
                @endcan
                <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
