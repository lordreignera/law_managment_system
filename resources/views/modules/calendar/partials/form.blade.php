@php
    $event = $event ?? null;
    $selectedBranchIds = collect(old('branch_ids', $event?->branches?->pluck('id')->all() ?: ($event?->branch_id ? [$event->branch_id] : (($defaultBranchId ?? null) ? [$defaultBranchId] : []))))
        ->map(fn ($branchId) => (string) $branchId)
        ->all();
    $selectedAssigneeIds = collect(old('assigned_to', $event?->assignees?->pluck('id')->all() ?: ($event?->assigned_to ? [$event->assigned_to] : [])))
        ->map(fn ($userId) => (string) $userId)
        ->all();
    $branchOptions = collect($branches)->map(fn ($branch) => [
        'value' => $branch->id,
        'label' => $branch->name,
        'search' => $branch->name,
        'attributes' => [
            'data-branch-id' => $branch->id,
            'data-branch-name' => $branch->name,
        ],
    ])->all();
    $assigneeOptions = collect($officers)->map(fn ($officer) => [
        'value' => $officer->id,
        'label' => $officer->name,
        'detail' => $officer->branch?->name,
        'search' => $officer->name.' '.$officer->branch?->name,
        'filter_value' => $officer->branch_id,
        'attributes' => [
            'data-user-branch-id' => $officer->branch_id,
            'data-assignee-name' => $officer->name,
        ],
    ])->all();
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

    @include('partials.check-dropdown', [
        'id' => 'calendar-branch-picker',
        'name' => 'branch_ids[]',
        'field' => 'branch_ids',
        'label' => 'Branches',
        'placeholder' => 'Select branches',
        'options' => $branchOptions,
        'selected' => $selectedBranchIds,
        'selectAllLabel' => 'Select all branches',
        'hint' => 'Choose every branch that should see this event.',
        'emptyLabel' => 'No branches available.',
    ])

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

    @include('partials.check-dropdown', [
        'id' => 'calendar-assignee-picker',
        'name' => 'assigned_to[]',
        'field' => 'assigned_to',
        'label' => 'Assigned To (optional)',
        'placeholder' => 'Unassigned',
        'options' => $assigneeOptions,
        'selected' => $selectedAssigneeIds,
        'selectAllLabel' => 'Select all users in selected branches',
        'hint' => 'Select branches first; only staff in those branches can be assigned.',
        'emptyLabel' => 'No users available.',
        'dependentOn' => 'calendar-branch-picker',
    ])

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
