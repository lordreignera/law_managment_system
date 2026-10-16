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
    <label><span>Sort Order</span><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $component->sort_order ?? 0) }}"></label>
    <div class="kfms-check-row"><label><input type="checkbox" name="is_taxable" value="1" @checked(old('is_taxable', $component->is_taxable))><span>Taxable</span></label><label><input type="checkbox" name="is_nssf_liable" value="1" @checked(old('is_nssf_liable', $component->is_nssf_liable))><span>NSSF liable</span></label><label><input type="checkbox" name="is_employer_cost" value="1" @checked(old('is_employer_cost', $component->is_employer_cost))><span>Employer cost</span></label></div>
    <div class="kfms-check-row"><label><input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring', $component->is_recurring))><span>Recurring</span></label><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $component->is_active))><span>Active</span></label></div>
</div>
<div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('payroll.components.index') }}">Cancel</a><button class="kfms-btn" type="submit">{{ $component->exists ? 'Update' : 'Create' }} Component</button></div>
