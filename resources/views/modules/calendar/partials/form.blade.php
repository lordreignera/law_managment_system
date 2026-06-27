@php $event = $event ?? null; @endphp

<div class="kfms-form-grid">
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

    <label>
        <span>Branch</span>
        <select name="branch_id">
            <option value="">My branch / firm-wide</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $event?->branch_id) == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_id') <small>{{ $message }}</small> @enderror
    </label>

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

    <label>
        <span>Assigned To (optional)</span>
        <select name="assigned_to">
            <option value="">Unassigned</option>
            @foreach ($officers as $officer)
                <option value="{{ $officer->id }}" @selected(old('assigned_to', $event?->assigned_to) == $officer->id)>{{ $officer->name }}</option>
            @endforeach
        </select>
        @error('assigned_to') <small>{{ $message }}</small> @enderror
    </label>

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

    <label class="kfms-checkbox">
        <input type="checkbox" name="all_day" value="1" @checked(old('all_day', $event?->all_day))>
        <span>All-day event</span>
    </label>

    <label class="kfms-span-2">
        <span>Notes (optional)</span>
        <textarea name="notes" rows="3" maxlength="2000">{{ old('notes', $event?->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>
</div>
