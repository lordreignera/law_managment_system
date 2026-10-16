@csrf
@if($rule->exists) @method('PUT') @endif
<div class="kfms-form-grid">
    <label><span>Code</span><input name="code" value="{{ old('code', $rule->code) }}" maxlength="60" required>@error('code')<small>{{ $message }}</small>@enderror</label>
    <label><span>Name</span><input name="name" value="{{ old('name', $rule->name) }}" maxlength="191" required>@error('name')<small>{{ $message }}</small>@enderror</label>
    <label><span>Rule Type</span><select name="rule_type" required>@foreach($types as $value => $label)<option value="{{ $value }}" @selected(old('rule_type', $rule->rule_type) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Calculation Method</span><select name="calculation_method" required>@foreach($methods as $value => $label)<option value="{{ $value }}" @selected(old('calculation_method', $rule->calculation_method) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Rate (%)</span><input type="number" name="rate" step="0.0001" min="0" max="100" value="{{ old('rate', $rule->rate) }}"></label>
    <label><span>Effective From</span><input type="date" name="effective_from" value="{{ old('effective_from', $rule->effective_from?->toDateString()) }}" required></label>
    <label><span>Effective To</span><input type="date" name="effective_to" value="{{ old('effective_to', $rule->effective_to?->toDateString()) }}"></label>
    <label><span>Sort Order</span><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $rule->sort_order ?? 0) }}"></label>
    <label class="kfms-span-2"><span>Formula (optional)</span><textarea name="formula" rows="3">{{ old('formula', $rule->formula) }}</textarea>@error('formula')<small>{{ $message }}</small>@enderror</label>
    <label class="kfms-span-2"><span>Configuration JSON (optional)</span><textarea name="configuration" rows="8" placeholder='For tax bands, store a versioned JSON object such as {"bands": []}'>{{ old('configuration', $rule->configuration ? json_encode($rule->configuration, JSON_PRETTY_PRINT) : '') }}</textarea>@error('configuration')<small>{{ $message }}</small>@enderror</label>
    <label class="kfms-span-2"><span>Description</span><textarea name="description" rows="3">{{ old('description', $rule->description) }}</textarea></label>
    @php
        $ruleFlags = old('flags', collect(\App\Models\PayrollRule::FLAGS)->filter(function ($label, $key) use ($rule) {
            return match ($key) {
                'active' => (bool) $rule->is_active,
                'employer_cost' => (bool) ($rule->is_employer_cost ?? false),
                default => false,
            };
        })->keys()->all());
        $ruleFlagOptions = [
            ['value' => 'active', 'label' => 'Active', 'detail' => 'Use this rule in eligible payroll calculations.'],
            ['value' => 'employer_cost', 'label' => 'Employer cost', 'detail' => 'Treat the result as a firm cost rather than an employee deduction.'],
        ];
    @endphp
    @include('partials.check-dropdown', [
        'id' => 'payroll-rule-flags-picker',
        'name' => 'flags[]',
        'field' => 'flags',
        'label' => 'Rule flags',
        'placeholder' => 'Select rule flags',
        'options' => $ruleFlagOptions,
        'selected' => $ruleFlags,
        'selectAllLabel' => 'Select all flags',
        'hint' => 'Choose the behaviors that apply to this rule.',
        'wrapperClass' => 'kfms-span-2',
    ])
</div>
<div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('payroll.rules.index') }}">Cancel</a><button class="kfms-btn" type="submit">{{ $rule->exists ? 'Update' : 'Create' }} Rule</button></div>
