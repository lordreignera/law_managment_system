<div class="kfms-form-grid">
    <label>
        <span>Department Name</span>
        <input type="text" name="name" value="{{ old('name', $department->name) }}" maxlength="191" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Department Code</span>
        <input type="text" value="{{ $department->code ?: 'Generated on save' }}" readonly>
        <small>Codes are generated automatically.</small>
    </label>

    <label>
        <span>Sort Order</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $department->sort_order ?? 0) }}" min="0" max="9999">
        @error('sort_order') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-switch-field">
        <span>Department status</span>
        <span class="kfms-switch-control">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department->is_active ?? true))>
            <span class="kfms-switch" aria-hidden="true"></span>
            <strong>Active</strong>
        </span>
    </label>

    <label class="kfms-span-2">
        <span>Description</span>
        <textarea name="description" rows="4" maxlength="1000">{{ old('description', $department->description) }}</textarea>
        @error('description') <small>{{ $message }}</small> @enderror
    </label>
</div>
