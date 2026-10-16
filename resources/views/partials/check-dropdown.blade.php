@php
    $selectedValues = array_map('strval', $selected ?? []);
    $fieldName = $name;
    $fieldKey = $field;
    $pickerId = $id ?? 'check-'.$fieldKey.'-picker';
    $options = $options ?? [];
    $wrapperClass = trim('kfms-check-dropdown-field '.($wrapperClass ?? ''));
@endphp

<div class="{{ $wrapperClass }}">
    <span class="kfms-form-label" id="{{ $pickerId }}-label">{{ $label }}</span>
    <div
        class="kfms-check-dropdown"
        id="{{ $pickerId }}"
        data-check-dropdown
        data-placeholder="{{ $placeholder }}"
        @if (! empty($dependentOn)) data-check-dependent-on="{{ $dependentOn }}" @endif
    >
        <button
            class="kfms-check-dropdown-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="{{ $pickerId }}-menu"
            aria-labelledby="{{ $pickerId }}-label {{ $pickerId }}-summary"
            data-check-dropdown-toggle
        >
            <span id="{{ $pickerId }}-summary" data-check-summary>{{ $placeholder }}</span>
            <i class="mdi mdi-chevron-down" aria-hidden="true"></i>
        </button>

        <div class="kfms-check-dropdown-menu" id="{{ $pickerId }}-menu" role="group" aria-labelledby="{{ $pickerId }}-label" data-check-dropdown-menu>
            <label class="kfms-check-dropdown-select-all">
                <input type="checkbox" data-check-all>
                <span>{{ $selectAllLabel ?? 'Select all' }}</span>
            </label>

            <div class="kfms-check-dropdown-search">
                <i class="mdi mdi-magnify" aria-hidden="true"></i>
                <input type="search" placeholder="Search {{ strtolower($label) }}" data-check-search aria-label="Search {{ $label }}">
            </div>

            <div class="kfms-check-dropdown-options" data-check-options>
                @forelse ($options as $option)
                    @php
                        $value = (string) ($option['value'] ?? '');
                        $optionLabel = (string) ($option['label'] ?? $value);
                        $optionDetail = $option['detail'] ?? null;
                        $searchText = strtolower((string) ($option['search'] ?? ($optionLabel.' '.($optionDetail ?? ''))));
                    @endphp
                    <label
                        class="kfms-check-dropdown-option"
                        data-check-option-label
                        data-search-text="{{ $searchText }}"
                        @if (isset($option['filter_value'])) data-check-filter-value="{{ $option['filter_value'] }}" @endif
                        @foreach (($option['attributes'] ?? []) as $attribute => $attributeValue)
                            {{ $attribute }}="{{ $attributeValue }}"
                        @endforeach
                    >
                        <input
                            type="checkbox"
                            name="{{ $fieldName }}"
                            value="{{ $value }}"
                            data-check-option
                            @checked(in_array($value, $selectedValues, true))
                        >
                        <span>
                            <strong>{{ $optionLabel }}</strong>
                            @if ($optionDetail)
                                <small>{{ $optionDetail }}</small>
                            @endif
                        </span>
                    </label>
                @empty
                    <p class="kfms-check-dropdown-empty">{{ $emptyLabel ?? 'No options available.' }}</p>
                @endforelse
            </div>
        </div>
    </div>
    <small class="kfms-check-dropdown-hint">{{ $hint ?? 'Select one or more options.' }}</small>
    @error($fieldKey) <small>{{ $message }}</small> @enderror
    @error($fieldKey.'.*') <small>{{ $message }}</small> @enderror
</div>
