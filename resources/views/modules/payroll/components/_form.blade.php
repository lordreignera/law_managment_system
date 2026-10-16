@csrf
@if ($component->exists) @method('PUT') @endif
<div class="kfms-form-grid">
    <label><span>Code</span><input name="code" value="{{ old('code', $component->code) }}" maxlength="60" required>@error('code')<small>{{ $message }}</small>@enderror</label>
    <label><span>Name</span><input name="name" value="{{ old('name', $component->name) }}" maxlength="191" required>@error('name')<small>{{ $message }}</small>@enderror</label>
    <label><span>Component Type</span><select name="component_type" required>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('component_type', $component->component_type) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Calculation Method</span><select name="calculation_method" required>@foreach ($methods as $value => $label)<option value="{{ $value }}" @selected(old('calculation_method', $component->calculation_method) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Calculation Basis</span><select name="calculation_basis" required>@foreach ($bases as $value => $label)<option value="{{ $value }}" @selected(old('calculation_basis', $component->calculation_basis) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Fixed Amount</span><input type="number" name="amount" step="0.01" min="0" value="{{ old('amount', $component->amount) }}">@error('amount')<small>{{ $message }}</small>@enderror</label>
    <label><span>Rate (%)</span><input type="number" name="rate" step="0.0001" min="0" max="100" value="{{ old('rate', $component->rate) }}">@error('rate')<small>{{ $message }}</small>@enderror</label>
    <label class="kfms-span-2"><span>Formula (optional)</span><textarea name="formula" rows="3" placeholder="Use configured payroll variables when the calculation method is Custom Formula">{{ old('formula', $component->formula) }}</textarea>@error('formula')<small>{{ $message }}</small>@enderror</label>
    <label class="kfms-span-2"><span>Description</span><textarea name="description" rows="3">{{ old('description', $component->description) }}</textarea>@error('description')<small>{{ $message }}</small>@enderror</label>
    @php
        $componentFlags = old('flags', collect(\App\Models\PayrollComponent::FLAGS)->filter(function ($label, $key) use ($component) {
            return match ($key) {
                'taxable' => (bool) $component->is_taxable,
                'nssf_liable' => (bool) $component->is_nssf_liable,
                'employer_cost' => (bool) $component->is_employer_cost,
                'recurring' => (bool) $component->is_recurring,
                'active' => (bool) $component->is_active,
                default => false,
            };
        })->keys()->all());
        $componentFlagOptions = [
            ['value' => 'taxable', 'label' => 'Taxable', 'detail' => 'Include this earning in taxable pay.'],
            ['value' => 'nssf_liable', 'label' => 'NSSF liable', 'detail' => 'Include this component in the NSSF basis.'],
            ['value' => 'employer_cost', 'label' => 'Employer cost', 'detail' => 'Paid by the firm and excluded from employee deductions.'],
            ['value' => 'recurring', 'label' => 'Recurring', 'detail' => 'Offer this component for recurring salary assignments.'],
            ['value' => 'active', 'label' => 'Active', 'detail' => 'Make the component available for new payroll runs.'],
        ];
    @endphp
    @include('partials.check-dropdown', [
        'id' => 'payroll-component-flags-picker',
        'name' => 'flags[]',
        'field' => 'flags',
        'label' => 'Component flags',
        'placeholder' => 'Select component flags',
        'options' => $componentFlagOptions,
        'selected' => $componentFlags,
        'selectAllLabel' => 'Select all flags',
        'hint' => 'Choose the behaviors that apply. The list opens as a searchable, scrollable menu.',
    ])
    <label><span>Sort Order</span><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $component->sort_order ?? 0) }}"></label>
</div>
<div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('payroll.components.index') }}">Cancel</a><button class="kfms-btn" type="submit">{{ $component->exists ? 'Update' : 'Create' }} Component</button></div>
