@php
    $event = $event ?? null;
    $selectedBranchIds = collect(old('branch_ids', $event?->branches?->pluck('id')->all() ?: ($event?->branch_id ? [$event->branch_id] : (($defaultBranchId ?? null) ? [$defaultBranchId] : []))))
        ->map(fn ($branchId) => (string) $branchId)
        ->all();
    $selectedAssigneeIds = collect(old('assigned_to', $event?->assignees?->pluck('id')->all() ?: ($event?->assigned_to ? [$event->assigned_to] : [])))
        ->map(fn ($userId) => (string) $userId)
        ->all();
@endphp

<div class="kfms-form-grid kfms-calendar-form">
    <label class="kfms-span-2">
        <span>Title</span>
        <input type="text" name="title" value="{{ old('title', $event?->title) }}" maxlength="191" required>
        @error('title') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Type</span>
        <select name="type" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $event?->type ?? 'meeting') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <small>{{ $message }}</small> @enderror
    </label>

    @isset($statuses)
        <label>
            <span>Status</span>
            <select name="status" required>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $event?->status ?? 'scheduled') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status') <small>{{ $message }}</small> @enderror
        </label>
    @endisset

    <div class="kfms-calendar-branches">
        <span class="kfms-form-label" id="calendar-branches-label">Branches</span>
        <details class="kfms-branch-select" data-branch-select>
            <summary aria-labelledby="calendar-branches-label calendar-branches-selection">
                <span id="calendar-branches-selection" data-branch-selection>{{ $branches->filter(fn ($branch) => in_array((string) $branch->id, $selectedBranchIds, true))->pluck('name')->implode(', ') ?: 'Select branches' }}</span>
                <i class="mdi mdi-chevron-down" aria-hidden="true"></i>
            </summary>
            <div class="kfms-branch-options" role="group" aria-labelledby="calendar-branches-label">
                @forelse ($branches as $branch)
                    <label class="kfms-branch-option">
                        <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" data-branch-id="{{ $branch->id }}" data-branch-name="{{ $branch->name }}" @checked(in_array((string) $branch->id, $selectedBranchIds, true))>
                        <span>{{ $branch->name }}</span>
                    </label>
                @empty
                    <span class="kfms-branch-empty">No branches available</span>
                @endforelse
            </div>
        </details>
        @error('branch_ids') <small>{{ $message }}</small> @enderror
        @error('branch_ids.*') <small>{{ $message }}</small> @enderror
    </div>

    <label>
        <span>Related Matter (optional)</span>
        <select name="matter_id">
            <option value="">None</option>
            @foreach ($matters as $matter)
                <option value="{{ $matter->id }}" @selected(old('matter_id', $event?->matter_id) == $matter->id)>{{ $matter->reference_no }} — {{ \Illuminate\Support\Str::limit($matter->title, 40) }}</option>
            @endforeach
        </select>
        @error('matter_id') <small>{{ $message }}</small> @enderror
    </label>

    <div class="kfms-calendar-assignees">
        <span class="kfms-form-label" id="calendar-assignees-label">Assigned To (optional)</span>
        <details class="kfms-branch-select kfms-assignee-select" data-assignee-select>
            <summary aria-labelledby="calendar-assignees-label calendar-assignees-selection">
                <span id="calendar-assignees-selection" data-assignee-selection>{{ $officers->filter(fn ($officer) => in_array((string) $officer->id, $selectedAssigneeIds, true))->pluck('name')->implode(', ') ?: 'Unassigned' }}</span>
                <i class="mdi mdi-chevron-down" aria-hidden="true"></i>
            </summary>
            <div class="kfms-branch-options" role="group" aria-labelledby="calendar-assignees-label">
                <label class="kfms-branch-option kfms-assignee-select-all">
                    <input type="checkbox" data-assignee-select-all>
                    <span>Select all users in selected branches</span>
                </label>
                <div class="kfms-assignee-list" data-assignee-list>
                    @forelse ($officers as $officer)
                        <label class="kfms-branch-option" data-assignee-option data-user-branch-id="{{ $officer->branch_id }}">
                            <input type="checkbox" name="assigned_to[]" value="{{ $officer->id }}" data-assignee-name="{{ $officer->name }}" @checked(in_array((string) $officer->id, $selectedAssigneeIds, true))>
                            <span>{{ $officer->name }} <small>{{ $officer->branch?->name }}</small></span>
                        </label>
                    @empty
                        <span class="kfms-branch-empty">No users available</span>
                    @endforelse
                </div>
                <span class="kfms-branch-empty" data-assignee-empty hidden>Select a branch first</span>
            </div>
        </details>
        @error('assigned_to') <small>{{ $message }}</small> @enderror
        @error('assigned_to.*') <small>{{ $message }}</small> @enderror
    </div>

    <label>
        <span>Location (optional)</span>
        <input type="text" name="location" value="{{ old('location', $event?->location) }}" maxlength="191">
        @error('location') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Starts At</span>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $event?->starts_at?->format('Y-m-d\TH:i') ?? ($defaultDate ?? null) . 'T09:00') }}" required>
        @error('starts_at') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Ends At (optional)</span>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $event?->ends_at?->format('Y-m-d\TH:i')) }}">
        @error('ends_at') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-calendar-check">
        <input type="checkbox" name="all_day" value="1" @checked(old('all_day', $event?->all_day))>
        <span>All-day event</span>
    </label>

    <label class="kfms-span-2">
        <span>Notes (optional)</span>
        <textarea name="notes" rows="3" maxlength="2000">{{ old('notes', $event?->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>
</div>

@push('scripts')
    <script>
        const branchDropdown = document.querySelector('[data-branch-select]');
        const assigneeDropdown = document.querySelector('[data-assignee-select]');

        if (branchDropdown) {
            const summary = branchDropdown.querySelector('summary');
            const selection = branchDropdown.querySelector('[data-branch-selection]');
            const update = () => {
                const names = [...branchDropdown.querySelectorAll('input:checked')].map((input) => input.dataset.branchName);
                selection.textContent = names.join(', ') || 'Select branches';
                summary.title = selection.textContent;
            };

            branchDropdown.addEventListener('change', () => {
                update();
                updateAssignees();
            });
            branchDropdown.addEventListener('keydown', closeOnEscape);
            branchDropdown.addEventListener('focusout', closeOnFocusOut);
            update();
        }

        if (assigneeDropdown) {
            const summary = assigneeDropdown.querySelector('summary');
            assigneeDropdown.addEventListener('change', updateAssignees);
            assigneeDropdown.addEventListener('keydown', closeOnEscape);
            assigneeDropdown.addEventListener('focusout', closeOnFocusOut);
            assigneeDropdown.querySelector('[data-assignee-select-all]')?.addEventListener('change', (event) => {
                assigneeDropdown.querySelectorAll('[data-assignee-option]:not([hidden]) input').forEach((input) => {
                    input.checked = event.target.checked;
                });
                updateAssigneeSummary();
            });
            updateAssignees();
            updateAssigneeSummary();
            summary.title = assigneeDropdown.querySelector('[data-assignee-selection]').textContent;
        }

        function selectedBranchIds() {
            const checkedBranches = branchDropdown ? [...branchDropdown.querySelectorAll('input:checked')] : [];

            return new Set(checkedBranches.map((input) => input.dataset.branchId));
        }

        function updateAssignees() {
            if (!assigneeDropdown) return;

            const branchIds = selectedBranchIds();
            const options = [...assigneeDropdown.querySelectorAll('[data-assignee-option]')];
            const emptyState = assigneeDropdown.querySelector('[data-assignee-empty]');
            let visibleCount = 0;
            let selectedCount = 0;

            options.forEach((option) => {
                const visible = branchIds.has(option.dataset.userBranchId);
                const input = option.querySelector('input');
                option.hidden = !visible;
                input.disabled = !visible;

                if (!visible) input.checked = false;
                if (visible) {
                    visibleCount++;
                    if (input.checked) selectedCount++;
                }
            });

            if (emptyState) emptyState.hidden = visibleCount > 0;

            const selectAll = assigneeDropdown.querySelector('[data-assignee-select-all]');
            if (selectAll) {
                selectAll.disabled = visibleCount === 0;
                selectAll.checked = visibleCount > 0 && selectedCount === visibleCount;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < visibleCount;
            }

            updateAssigneeSummary();
        }

        function updateAssigneeSummary() {
            if (!assigneeDropdown) return;

            const names = [...assigneeDropdown.querySelectorAll('[data-assignee-option] input:checked')]
                .map((input) => input.dataset.assigneeName);
            const selection = assigneeDropdown.querySelector('[data-assignee-selection]');
            const summary = assigneeDropdown.querySelector('summary');
            selection.textContent = names.join(', ') || 'Unassigned';
            summary.title = selection.textContent;
        }

        function closeOnEscape(event) {
            if (event.key !== 'Escape') return;
            event.currentTarget.open = false;
            event.currentTarget.querySelector('summary').focus();
        }

        function closeOnFocusOut(event) {
            if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) event.currentTarget.open = false;
        }

        document.addEventListener('click', (event) => {
            document.querySelectorAll('[data-branch-select], [data-assignee-select]').forEach((dropdown) => {
                if (!dropdown.contains(event.target)) dropdown.open = false;
            });
        });
    </script>
@endpush
