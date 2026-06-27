@php $holiday = $holiday ?? null; @endphp

<div class="kfms-form-grid">
    <label>
        <span>Holiday Name</span>
        <input type="text" name="name" value="{{ old('name', $holiday?->name) }}" maxlength="191" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Date</span>
        <input type="date" name="date" value="{{ old('date', $holiday?->date?->format('Y-m-d')) }}" required>
        @error('date') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-checkbox">
        <input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring', $holiday?->is_recurring))>
        <span>Recurs on the same date every year</span>
    </label>

    <label class="kfms-span-2">
        <span>Notes (optional)</span>
        <textarea name="notes" rows="2" maxlength="1000">{{ old('notes', $holiday?->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>
</div>
