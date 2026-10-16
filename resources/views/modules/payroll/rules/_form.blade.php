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
    <div class="kfms-check-row"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active))><span>Active</span></label><label><input type="checkbox" name="is_employer_cost" value="1" @checked(old('is_employer_cost', $rule->is_employer_cost ?? false))><span>Employer cost (do not deduct)</span></label></div>
</div>
<div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('payroll.rules.index') }}">Cancel</a><button class="kfms-btn" type="submit">{{ $rule->exists ? 'Update' : 'Create' }} Rule</button></div>
