@php
    $event = $event ?? null;
    $selectedMatterId = $selectedMatterId ?? null;
@endphp

<div class="kfms-form-grid">
    <label>
        <span>Related Matter</span>
        <select name="matter_id" required>
            <option value="">Select matter</option>
            @foreach ($matters as $matter)
                <option value="{{ $matter->id }}" @selected((string) old('matter_id', $event?->matter_id ?? $selectedMatterId) === (string) $matter->id)>
                    {{ $matter->reference_no }} - {{ \Illuminate\Support\Str::limit($matter->title, 40) }}
                </option>
            @endforeach
        </select>
        @error('matter_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Event Type</span>
        <select name="event_type" required>
            @foreach ($eventTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('event_type', $event?->event_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('event_type') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Court</span>
        <select name="court_id">
            <option value="">Select court</option>
            @foreach ($courts as $court)
                <option value="{{ $court->id }}" @selected(old('court_id', $event?->court_id) == $court->id)>{{ $court->name }}</option>
            @endforeach
        </select>
        @error('court_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Court Name (if not listed)</span>
        <input type="text" name="court_name" value="{{ old('court_name', $event?->court_name) }}" maxlength="255">
        @error('court_name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Case Number</span>
        <input type="text" name="case_number" value="{{ old('case_number', $event?->case_number) }}" maxlength="255">
        @error('case_number') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Judicial Officer</span>
        <input type="text" name="judicial_officer" value="{{ old('judicial_officer', $event?->judicial_officer) }}" maxlength="255">
        @error('judicial_officer') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Assigned Advocate</span>
        <select name="assigned_to">
            <option value="">Unassigned</option>
            @foreach ($officers as $officer)
                <option value="{{ $officer->id }}" @selected(old('assigned_to', $event?->assigned_to) == $officer->id)>{{ $officer->name }}</option>
            @endforeach
        </select>
        @error('assigned_to') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Status</span>
        <select name="status" required>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $event?->status ?? 'scheduled') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Starts At</span>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($event?->starts_at)->format('Y-m-d\TH:i')) }}" required>
        @error('starts_at') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Ends At (optional)</span>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($event?->ends_at)->format('Y-m-d\TH:i')) }}">
        @error('ends_at') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Next Step (optional)</span>
        <input type="text" name="next_step" value="{{ old('next_step', $event?->next_step) }}" maxlength="255">
        @error('next_step') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Next Step Due (deadline)</span>
        <input type="date" name="next_step_due" value="{{ old('next_step_due', optional($event?->next_step_due)->format('Y-m-d')) }}">
        @error('next_step_due') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Notes</span>
        <textarea name="notes" rows="3" placeholder="Instructions or context for this appearance">{{ old('notes', $event?->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Court Document (optional)</span>
        <input type="file" name="attachment">
        @error('attachment') <small>{{ $message }}</small> @enderror
    </label>
</div>
