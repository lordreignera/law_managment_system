@csrf
@if($profile->exists) @method('PUT') @endif
<div class="kfms-section-heading"><div><h3>Employee and statutory details</h3><span>Keep sensitive payroll identity and payment information restricted.</span></div></div>
<div class="kfms-form-grid">
    @if(!$profile->exists)
        <label class="kfms-span-2"><span>Staff Member</span><select name="user_id" required><option value="">Select active staff</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('user_id') == $employee->id)>{{ $employee->name }} · {{ $employee->staffProfile?->staff_no ?: $employee->email }} · {{ $employee->department?->name ?: 'No department' }}</option>@endforeach</select>@error('user_id')<small>{{ $message }}</small>@enderror</label>
    @else
        <label><span>Staff Member</span><input value="{{ $employee?->name }}" readonly disabled></label>
        <label><span>Staff Number</span><input value="{{ $employee?->staffProfile?->staff_no }}" readonly disabled></label>
    @endif
    <label><span>TIN</span><input name="tin" value="{{ old('tin', $profile->tin) }}" maxlength="80"></label>
    <label><span>NSSF Number</span><input name="nssf_number" value="{{ old('nssf_number', $profile->nssf_number) }}" maxlength="80"></label>
    <label><span>Tax Residency</span><select name="tax_residency" required>@foreach($residencies as $value => $label)<option value="{{ $value }}" @selected(old('tax_residency', $profile->tax_residency) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Payment Method</span><select name="payment_method" required>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', $profile->payment_method) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Bank</span><select name="bank_id"><option value="">Select bank</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" @selected(old('bank_id', $profile->bank_id) == $bank->id)>{{ $bank->name }}</option>@endforeach</select></label>
    <label><span>Bank Branch</span><select name="bank_branch_id"><option value="">Select branch</option>@foreach($bankBranches as $branch)<option value="{{ $branch->id }}" @selected(old('bank_branch_id', $profile->bank_branch_id) == $branch->id)>{{ $branch->bank?->name }} · {{ $branch->name }}</option>@endforeach</select></label>
    <label><span>Account Name</span><input name="account_name" value="{{ old('account_name', $profile->account_name) }}" maxlength="191"></label>
    <label><span>Account Number</span><input name="account_number" value="{{ old('account_number', $profile->account_number) }}" maxlength="100"></label>
    <label><span>Profile Effective From</span><input type="date" name="effective_from" value="{{ old('effective_from', $profile->effective_from?->toDateString()) }}"></label>
    <label><span>Profile Effective To</span><input type="date" name="effective_to" value="{{ old('effective_to', $profile->effective_to?->toDateString()) }}"></label>
    <label class="kfms-span-2"><span>Profile Notes</span><textarea name="notes" rows="2">{{ old('notes', $profile->notes) }}</textarea></label>
    <div class="kfms-check-row"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $profile->is_active))><span>Included in future payroll periods</span></label></div>
</div>

<div class="kfms-section-heading"><div><h3>Current salary record</h3><span>Salary versions are effective-dated so historical payroll can be reproduced.</span></div></div>
<div class="kfms-form-grid">
    <label><span>Base Salary</span><input type="number" name="base_salary" step="0.01" min="0" value="{{ old('base_salary', $salary->base_salary) }}" required>@error('base_salary')<small>{{ $message }}</small>@enderror</label>
    <label><span>Currency</span><select name="currency_type_id"><option value="">Select currency</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_type_id', $salary->currency_type_id) == $currency->id)>{{ $currency->name }} ({{ $currency->iso_code }})</option>@endforeach</select></label>
    <label><span>Pay Frequency</span><select name="pay_frequency" required>@foreach($frequencies as $value => $label)<option value="{{ $value }}" @selected(old('pay_frequency', $salary->pay_frequency) === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span>Salary Effective From</span><input type="date" name="salary_effective_from" value="{{ old('salary_effective_from', $salary->effective_from?->toDateString()) }}" required></label>
    <label><span>Salary Effective To</span><input type="date" name="salary_effective_to" value="{{ old('salary_effective_to', $salary->effective_to?->toDateString()) }}"></label>
    <label class="kfms-span-2"><span>Salary Notes</span><textarea name="salary_notes" rows="2">{{ old('salary_notes', $salary->notes) }}</textarea></label>
</div>

<div class="kfms-section-heading"><div><h3>Recurring components</h3><span>Choose the components used by this employee and override their amount or rate where required.</span></div></div>
<div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Use</th><th>Component</th><th>Default calculation</th><th>Employee amount</th><th>Employee rate (%)</th></tr></thead><tbody>
@forelse($components as $component)
    @php($assignment = $assignedComponents->get($component->id))
    <tr><td><input type="checkbox" name="component_ids[]" value="{{ $component->id }}" @checked($assignment)></td><td><strong>{{ $component->name }}</strong><br><small>{{ $component->typeLabel() }}</small></td><td>{{ $component->calculation_method === 'percentage' ? number_format((float)$component->rate, 4).'%' : number_format((float)$component->amount, 2) }} of {{ str($component->calculation_basis)->headline() }}</td><td><input type="number" step="0.01" min="0" name="component_amounts[{{ $component->id }}]" value="{{ old('component_amounts.'.$component->id, $assignment?->amount) }}"></td><td><input type="number" step="0.0001" min="0" max="100" name="component_rates[{{ $component->id }}]" value="{{ old('component_rates.'.$component->id, $assignment?->rate) }}"></td></tr>
@empty<tr><td colspan="5" class="kfms-empty">Configure payroll components before assigning them.</td></tr>@endforelse
</tbody></table></div>
<div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('payroll.employees.index') }}">Cancel</a><button class="kfms-btn" type="submit">{{ $profile->exists ? 'Update' : 'Create' }} Payroll Profile</button></div>
