@php
    $selectedValues = array_map('strval', $selected ?? []);
    $sortOrders = $sortOrders ?? [];
    $pickerId = $id ?? 'bundle-attachment-picker';
@endphp

<div class="kfms-check-dropdown-field kfms-span-2">
    <span class="kfms-form-label" id="{{ $pickerId }}-label">{{ $label }}</span>
    <div class="kfms-check-dropdown" id="{{ $pickerId }}" data-check-dropdown data-placeholder="{{ $placeholder }}">
        <button class="kfms-check-dropdown-toggle" type="button" aria-expanded="false" aria-controls="{{ $pickerId }}-menu" aria-labelledby="{{ $pickerId }}-label {{ $pickerId }}-summary" data-check-dropdown-toggle>
            <span id="{{ $pickerId }}-summary" data-check-summary>{{ $placeholder }}</span>
            <i class="mdi mdi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="kfms-check-dropdown-menu" id="{{ $pickerId }}-menu" role="group" aria-labelledby="{{ $pickerId }}-label" data-check-dropdown-menu>
            <label class="kfms-check-dropdown-select-all">
                <input type="checkbox" data-check-all>
                <span>Select all documents</span>
            </label>
            <div class="kfms-check-dropdown-search">
                <i class="mdi mdi-magnify" aria-hidden="true"></i>
                <input type="search" placeholder="Search documents" data-check-search aria-label="Search documents">
            </div>
            <div class="kfms-check-dropdown-options" data-check-options>
                @forelse ($attachments as $attachment)
                    @php
                        $attachmentLabel = $attachment->title ?: $attachment->original_name;
                        $attachmentDetail = str($attachment->category ?: 'document')->headline().' / v'.$attachment->version_number;
                    @endphp
                    <label class="kfms-check-dropdown-option kfms-check-dropdown-order-option" data-check-option-label data-search-text="{{ strtolower($attachmentLabel.' '.$attachmentDetail) }}">
                        <input type="checkbox" name="attachment_ids[]" value="{{ $attachment->id }}" data-check-option @checked(in_array((string) $attachment->id, $selectedValues, true))>
                        <span>
                            <strong>{{ $attachmentLabel }}</strong>
                            <small>{{ $attachmentDetail }}</small>
                        </span>
                        <input class="kfms-check-dropdown-order" type="number" name="sort_orders[{{ $attachment->id }}]" value="{{ $sortOrders[$attachment->id] ?? $loop->iteration }}" min="1" max="9999" aria-label="Order for {{ $attachmentLabel }}">
                    </label>
                @empty
                    <p class="kfms-check-dropdown-empty">Upload current matter documents before creating a bundle.</p>
                @endforelse
            </div>
        </div>
    </div>
    <small class="kfms-check-dropdown-hint">Select documents and set the order in which they should appear.</small>
    @error('attachment_ids') <small>{{ $message }}</small> @enderror
    @error('attachment_ids.*') <small>{{ $message }}</small> @enderror
</div>
