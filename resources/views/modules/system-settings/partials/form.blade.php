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

    @if (in_array('court_id', $extraFields, true))
        <label>
            <span>Court</span>
            <select name="court_id">
                <option value="">Select court</option>
                @foreach (($courts ?? []) as $court)
                    <option value="{{ $court->id }}" @selected((string) old('court_id', $record->court_id) === (string) $court->id)>{{ $court->name }}</option>
                @endforeach
            </select>
            @error('court_id') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('title', $extraFields, true))
        <label>
            <span>Title / Rank</span>
            <input type="text" name="title" value="{{ old('title', $record->title) }}">
            @error('title') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('chambers', $extraFields, true))
        <label>
            <span>Chambers</span>
            <input type="text" name="chambers" value="{{ old('chambers', $record->chambers) }}">
            @error('chambers') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('firm_name', $extraFields, true))
        <label>
            <span>Firm</span>
            <input type="text" name="firm_name" value="{{ old('firm_name', $record->firm_name) }}">
            @error('firm_name') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('symbol', $extraFields, true))
        <label>
            <span>Symbol</span>
            <input type="text" name="symbol" value="{{ old('symbol', $record->symbol) }}">
            @error('symbol') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('contact_person', $extraFields, true))
        <label>
            <span>Contact Person</span>
            <input type="text" name="contact_person" value="{{ old('contact_person', $record->contact_person) }}">
            @error('contact_person') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('email', $extraFields, true))
        <label>
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email', $record->email) }}">
            @error('email') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('phone', $extraFields, true))
        <label>
            <span>Phone</span>
            <input type="text" name="phone" value="{{ old('phone', $record->phone) }}">
            @error('phone') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('branch_id', $extraFields, true))
        <label>
            <span>Branch</span>
            <select name="branch_id">
                <option value="">All branches</option>
                @foreach (($branches ?? []) as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $record->branch_id) === (string) $branch->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('bank_id', $extraFields, true))
        <label>
            <span>Bank / Financial Institution</span>
            <select name="bank_id" required>
                <option value="">Select bank</option>
                @foreach (($banks ?? []) as $bank)
                    <option value="{{ $bank->id }}" @selected((string) old('bank_id', $record->bank_id) === (string) $bank->id)>
                        {{ $bank->name }}
                    </option>
                @endforeach
            </select>
            @error('bank_id') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('office_location', $extraFields, true))
        <label class="kfms-span-2">
            <span>Office Location</span>
            <input type="text" name="office_location" value="{{ old('office_location', $record->office_location) }}">
            @error('office_location') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('districts_covered', $extraFields, true))
        <label class="kfms-span-2">
            <span>Districts Covered</span>
            <textarea name="districts_covered" rows="3">{{ old('districts_covered', $record->districts_covered) }}</textarea>
            @error('districts_covered') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('centenary_region_id', $extraFields, true))
        <label>
            <span>Centenary Region</span>
            <select name="centenary_region_id" required>
                <option value="">Select region</option>
                @foreach (($centenaryRegions ?? []) as $region)
                    <option value="{{ $region->id }}" @selected((string) old('centenary_region_id', $record->centenary_region_id) === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </select>
            @error('centenary_region_id') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('portfolio_types', $extraFields, true))
        <label class="kfms-span-2">
            <span>Portfolio Types</span>
            <textarea name="portfolio_types" rows="4" placeholder="One portfolio type per line">{{ old('portfolio_types', implode("\n", $record->portfolio_types ?: [])) }}</textarea>
            @error('portfolio_types') <small>{{ $message }}</small> @enderror
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

    @if (in_array('tax_type', $extraFields, true))
        <label>
            <span>Tax Type</span>
            <select name="tax_type">
                @foreach (['vat' => 'VAT', 'withholding' => 'Withholding', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('tax_type', $record->tax_type ?: 'vat') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('tax_type') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('rate', $extraFields, true))
        <label>
            <span>Rate (%)</span>
            <input type="number" name="rate" min="0" max="100" step="0.0001" value="{{ old('rate', $record->rate ?? 0) }}" required>
            @error('rate') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('is_inclusive', $extraFields, true))
        <label class="kfms-check-row">
            <input type="checkbox" name="is_inclusive" value="1" @checked(old('is_inclusive', $record->is_inclusive))>
            <span>Tax inclusive</span>
        </label>
    @endif

    @if (in_array('channel', $extraFields, true))
        <label>
            <span>Channel</span>
            <select name="channel">
                @foreach (['database' => 'In-app', 'email' => 'Email', 'sms' => 'SMS'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('channel', $record->channel ?: 'database') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('channel') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('event_key', $extraFields, true))
        <label>
            <span>Event Key</span>
            <input type="text" name="event_key" value="{{ old('event_key', $record->event_key) }}" required>
            @error('event_key') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('subject', $extraFields, true))
        <label>
            <span>Subject</span>
            <input type="text" name="subject" value="{{ old('subject', $record->subject) }}">
            @error('subject') <small>{{ $message }}</small> @enderror
        </label>
    @endif

    @if (in_array('body', $extraFields, true))
        <label class="kfms-span-2">
            <span>Template Body</span>
            <textarea name="body" rows="5" required>{{ old('body', $record->body) }}</textarea>
            @error('body') <small>{{ $message }}</small> @enderror
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
