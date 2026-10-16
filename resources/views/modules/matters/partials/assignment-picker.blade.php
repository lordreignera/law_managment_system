@php
    $selectedValues = array_map('strval', $selected ?? []);
    $fieldName = $name;
    $fieldKey = $field;
    $pickerId = 'matter-'.$fieldKey.'-picker';
@endphp

<label class="kfms-check-dropdown-field">
    <span>{{ $label }}</span>
    <div class="kfms-check-dropdown" id="{{ $pickerId }}" data-check-dropdown data-placeholder="{{ $placeholder }}">
        <button class="kfms-check-dropdown-toggle" type="button" aria-expanded="false" aria-controls="{{ $pickerId }}-menu" data-check-dropdown-toggle>
            <span data-check-summary>{{ $placeholder }}</span>
            <i class="mdi mdi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="kfms-check-dropdown-menu" id="{{ $pickerId }}-menu" role="listbox" aria-label="{{ $label }}" data-check-dropdown-menu>
            <label class="kfms-check-dropdown-select-all">
                <input type="checkbox" data-check-all>
                <span>Select all {{ strtolower($label) }}</span>
            </label>
            <div class="kfms-check-dropdown-search">
                <i class="mdi mdi-magnify" aria-hidden="true"></i>
                <input type="search" placeholder="Search {{ strtolower($label) }}" data-check-search aria-label="Search {{ $label }}">
            </div>
            <div class="kfms-check-dropdown-options" data-check-options>
                @forelse ($users as $user)
                    @php
                        $roleLabel = $user->roles->pluck('name')->filter()->join(' / ') ?: 'Staff member';
                    @endphp
                    <label class="kfms-check-dropdown-option" data-check-option-label data-search-text="{{ strtolower($user->name.' '.$roleLabel) }}">
                        <input type="checkbox" name="{{ $fieldName }}" value="{{ $user->id }}" data-check-option @checked(in_array((string) $user->id, $selectedValues, true))>
                        <span>
                            <strong>{{ $user->name }}</strong>
                            <small>{{ $roleLabel }}</small>
                        </span>
                    </label>
                @empty
                    <p class="kfms-check-dropdown-empty">No active staff members available.</p>
                @endforelse
            </div>
        </div>
    </div>
    <small class="kfms-check-dropdown-hint">Select one or more people. Their system role is shown below each name.</small>
    @error($fieldKey) <small>{{ $message }}</small> @enderror
</label>
