@php
    $extraFields = $definition['extra_fields'] ?? [];
    $isModal = $isModal ?? false;
@endphp

<div class="kfms-form-grid">
    @if ($record->code)
        <label>
            <span>{{ $record->exists ? 'Code' : 'Generated Code' }}</span>
            <input type="text" value="{{ $record->code }}" disabled>
        </label>
    @endif

    <label>
        <span>Name</span>
        <input type="text" name="name" value="{{ old('name', $record->name) }}" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    @if (in_array('court_level', $extraFields, true))
        <label>
            <span>Court Level</span>
            <input type="text" name="court_level" value="{{ old('court_level', $record->court_level) }}">
            @error('court_level') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('station', $extraFields, true))
        <label>
            <span>Station</span>
            <input type="text" name="station" value="{{ old('station', $record->station) }}">
            @error('station') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('symbol', $extraFields, true))
        <label>
            <span>Symbol</span>
            <input type="text" name="symbol" value="{{ old('symbol', $record->symbol) }}">
            @error('symbol') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('hourly_rate', $extraFields, true))
        <label>
            <span>Hourly Rate</span>
            <input type="number" step="0.01" min="0" name="hourly_rate" value="{{ old('hourly_rate', $record->hourly_rate ?? 0) }}" required>
            @error('hourly_rate') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('currency_type_id', $extraFields, true))
        <label>
            <span>Currency</span>
            <select name="currency_type_id">
                <option value="">Select currency</option>
                @foreach ($currencyTypes as $currency)
                    <option value="{{ $currency->id }}" @selected((string) old('currency_type_id', $record->currency_type_id) === (string) $currency->id)>
                        {{ $currency->name }}
                    </option>
                @endforeach
            </select>
            @error('currency_type_id') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    <label>
        <span>Sort Order</span>
        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $record->sort_order ?? 0) }}">
        @error('sort_order') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Description</span>
        <textarea name="description" rows="3">{{ old('description', $record->description) }}</textarea>
        @error('description') <small>{{ $message }}</small> @enderror
    </label>

    @if (in_array('header_text', $extraFields, true))
        <label class="kfms-span-2">
            <span>Header Text</span>
            <textarea name="header_text" rows="3">{{ old('header_text', $record->header_text) }}</textarea>
            @error('header_text') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('footer_text', $extraFields, true))
        <label class="kfms-span-2">
            <span>Footer Text</span>
            <textarea name="footer_text" rows="3">{{ old('footer_text', $record->footer_text) }}</textarea>
            @error('footer_text') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    <label class="kfms-check-row kfms-span-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $record->exists ? $record->is_active : true))>
        <span>Active</span>
    </label>

    @if (in_array('is_default', $extraFields, true))
        <label class="kfms-check-row kfms-span-2">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $record->is_default))>
            <span>Default Letterhead</span>
        </label>
    @endif
</div>

<div class="kfms-form-actions">
    @if ($isModal)
        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
    @else
        <a class="kfms-link-btn" href="{{ route('settings.system.index', $setting) }}">Cancel</a>
    @endif
    <button type="submit">Save {{ $definition['singular'] }}</button>
</div>
