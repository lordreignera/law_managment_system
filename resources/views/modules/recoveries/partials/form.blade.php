@php
    $account = $account ?? null;
    $includeAssignment = $includeAssignment ?? true;
    $centenaryRegions = \App\Models\CentenaryRegion::where('is_active', true)->orderBy('name')->get();
    $centenaryBranches = \App\Models\CentenaryBranch::where('is_active', true)->with('region')->orderBy('name')->get();
    $bankBranches = \App\Models\BankBranch::where('is_active', true)->with('bank')->orderBy('name')->get();
@endphp

<div class="kfms-form-grid" data-recovery-reference-form>
    <label>
        <span>Bank / Client</span>
        <select name="recovery_client_id" required>
            <option value="">Select bank/client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" data-bank="{{ $client->name }}" data-centenary="{{ str($client->name)->contains('Centenary', true) ? '1' : '0' }}" @selected(old('recovery_client_id', $account?->recovery_client_id) == $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
        @error('recovery_client_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Status</span>
        <select name="status" required>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $account?->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Pipeline Stage</span>
        <select name="pipeline_stage" required>
            @foreach (\App\Models\RecoveryAccount::PIPELINE_STAGES as $value => $label)
                <option value="{{ $value }}" @selected(old('pipeline_stage', $account?->pipeline_stage ?? 'new') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('pipeline_stage') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Priority</span>
        <select name="priority" required>
            @foreach (\App\Models\RecoveryAccount::PRIORITIES as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $account?->priority ?? 'normal') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('priority') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Portfolio Type</span>
        <select name="portfolio_type">
            <option value="">Select configured portfolio</option>
            @foreach ($clients as $client)
                @foreach ($client->portfolioOptions() as $value => $label)
                    <option value="{{ $value }}" data-client="{{ $client->id }}" @selected(old('recovery_client_id', $account?->recovery_client_id) == $client->id && old('portfolio_type', $account?->portfolio_type) === $value)>{{ $label }}</option>
                @endforeach
            @endforeach
        </select>
        <small>Configure portfolio choices for each recovery client in System Settings.</small>
        @error('portfolio_type') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Debtor Name</span>
        <input type="text" name="debtor_name" value="{{ old('debtor_name', $account?->debtor_name) }}" maxlength="191" required>
        @error('debtor_name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Account Number</span>
        <input type="text" name="account_number" value="{{ old('account_number', $account?->account_number) }}" maxlength="100">
        @error('account_number') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Customer Number</span>
        <input type="text" name="customer_number" value="{{ old('customer_number', $account?->customer_number) }}" maxlength="100">
        @error('customer_number') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Debtor Phone Number</span>
        <input type="text" name="phone" value="{{ old('phone', $account?->phone) }}" maxlength="50">
        @error('phone') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Alternate Phone</span>
        <input type="text" name="alternate_phone" value="{{ old('alternate_phone', $account?->alternate_phone) }}" maxlength="50">
        @error('alternate_phone') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>WhatsApp Number</span>
        <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $account?->whatsapp_number) }}" maxlength="50">
        @error('whatsapp_number') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Email</span>
        <input type="email" name="email" value="{{ old('email', $account?->email) }}" maxlength="191">
        @error('email') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Employer</span>
        <input type="text" name="employer" value="{{ old('employer', $account?->employer) }}" maxlength="191">
        @error('employer') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>NIN</span>
        <input type="text" name="nin" value="{{ old('nin', $account?->nin) }}" maxlength="100">
        @error('nin') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>NSSF Number</span>
        <input type="text" name="nssf_number" value="{{ old('nssf_number', $account?->nssf_number) }}" maxlength="100">
        @error('nssf_number') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>CRB Reference</span>
        <input type="text" name="crb_reference" value="{{ old('crb_reference', $account?->crb_reference) }}" maxlength="100">
        @error('crb_reference') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Region</span>
        <select name="region" data-centenary-region>
            <option value="">Select Centenary region</option>
            @foreach ($centenaryRegions as $region)
                <option value="{{ $region->name }}" @selected(old('region', $account?->region) === $region->name)>{{ $region->name }}</option>
            @endforeach
        </select>
        <input type="text" name="region" data-other-region value="{{ old('region', $account?->region) }}" maxlength="191">
        @error('region') <small>{{ $message }}</small> @enderror
    </label>
    <label>
        <span>Bank Branch</span>
        <select name="branch_name">
            <option value="">Select bank branch</option>
            @foreach ($bankBranches as $bankBranch)
                <option value="{{ $bankBranch->name }}" data-bank="{{ $bankBranch->bank?->name }}" @selected(old('branch_name', $account?->branch_name) === $bankBranch->name)>{{ $bankBranch->name }}</option>
            @endforeach
            @foreach ($centenaryBranches as $bankBranch)
                <option value="{{ $bankBranch->name }}" data-centenary="1" data-region="{{ $bankBranch->region?->name }}" @selected(old('branch_name', $account?->branch_name) === $bankBranch->name)>{{ $bankBranch->name }}</option>
            @endforeach
        </select>
        @error('branch_name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Collector Name</span>
        <input type="text" name="collector_name" value="{{ old('collector_name', $account?->collector_name) }}" maxlength="191">
        @error('collector_name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Operative Account</span>
        <input type="text" name="operative_account" value="{{ old('operative_account', $account?->operative_account) }}" maxlength="100">
        @error('operative_account') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Days Past Due</span>
        <input type="number" min="0" name="days_past_due" value="{{ old('days_past_due', $account?->days_past_due) }}">
        @error('days_past_due') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Next Follow-up</span>
        <input type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at', $account?->next_follow_up_at?->format('Y-m-d\\TH:i')) }}">
        @error('next_follow_up_at') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Principal Amount</span>
        <input type="number" step="0.01" min="0" name="principal_amount" value="{{ old('principal_amount', $account?->principal_amount ?? 0) }}">
        @error('principal_amount') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Interest Amount</span>
        <input type="number" step="0.01" min="0" name="interest_amount" value="{{ old('interest_amount', $account?->interest_amount ?? 0) }}">
        @error('interest_amount') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Outstanding Amount</span>
        <input type="number" step="0.01" min="0" name="outstanding_amount" value="{{ old('outstanding_amount', $account?->outstanding_amount ?? 0) }}">
        @error('outstanding_amount') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Arrears Amount</span>
        <input type="number" step="0.01" min="0" name="arrears_amount" value="{{ old('arrears_amount', $account?->arrears_amount ?? 0) }}">
        @error('arrears_amount') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Currency</span>
        <select name="currency" required>
            @foreach (\App\Models\CurrencyType::where('is_active', true)->orderBy('sort_order')->get() as $currency)
                <option value="{{ $currency->iso_code }}" @selected(old('currency', $account?->currency ?? 'UGX') === $currency->iso_code)>{{ $currency->iso_code }} — {{ $currency->name }}</option>
            @endforeach
        </select>
        @error('currency') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Bucket / Portfolio (optional)</span>
        <input type="text" name="bucket" value="{{ old('bucket', $account?->bucket) }}" maxlength="100">
        @error('bucket') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Collateral Held</span>
        <textarea name="collateral_held" rows="3">{{ old('collateral_held', $account?->collateral_held) }}</textarea>
        @error('collateral_held') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Cause of Default / Comments</span>
        <textarea name="cause_of_default" rows="3">{{ old('cause_of_default', $account?->cause_of_default) }}</textarea>
        @error('cause_of_default') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Skip-trace Status</span>
        <select name="skip_trace_status" required>
            @foreach (\App\Models\RecoveryAccount::SKIP_TRACE_STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('skip_trace_status', $account?->skip_trace_status ?? 'not_started') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('skip_trace_status') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>SMS reminders</span>
        <select name="sms_opt_in">
            <option value="1" @selected(old('sms_opt_in', $account?->sms_opt_in ?? true))>Allowed</option>
            <option value="0" @selected(! old('sms_opt_in', $account?->sms_opt_in ?? true))>Do not send</option>
        </select>
        @error('sms_opt_in') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Skip-trace Notes</span>
        <textarea name="skip_trace_notes" rows="2" maxlength="2000">{{ old('skip_trace_notes', $account?->skip_trace_notes) }}</textarea>
        @error('skip_trace_notes') <small>{{ $message }}</small> @enderror
    </label>

    @if ($includeAssignment)
    <label>
        <span>Assign to Recovery Officer</span>
        <select name="assigned_to">
            <option value="">Unassigned</option>
            @foreach ($officers as $officer)
                <option value="{{ $officer->id }}" @selected(old('assigned_to', $account?->assigned_to) == $officer->id)>{{ $officer->name }}@if ($officer->branch) — {{ $officer->branch->name }} @endif</option>
            @endforeach
        </select>
        @error('assigned_to') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Branch (defaults to officer's branch)</span>
        <select name="branch_id">
            <option value="">Auto / firm-wide</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $account?->branch_id) == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_id') <small>{{ $message }}</small> @enderror
    </label>
    @endif
</div>
<script>
(() => {
    const grid = document.currentScript.previousElementSibling;
    const client = grid.querySelector('[name="recovery_client_id"]');
    const portfolio = grid.querySelector('[name="portfolio_type"]');
    const branch = grid.querySelector('[name="branch_name"]');
    const region = grid.querySelector('[data-centenary-region]');
    const otherRegion = grid.querySelector('[data-other-region]');
    const filter = (select, predicate) => {
        const currentValue = select.value;
        Array.from(select.options).forEach(option => { option.hidden = option.disabled = !!option.value && !predicate(option); });
        const validIndex = Array.from(select.options).findIndex(option => option.value === currentValue && !option.disabled);
        select.selectedIndex = validIndex >= 0 ? validIndex : 0;
    };
    const refresh = () => {
        const option = client.selectedOptions[0];
        const centenary = option?.dataset.centenary === '1';
        region.hidden = region.disabled = !centenary;
        otherRegion.hidden = otherRegion.disabled = centenary;
        filter(portfolio, item => item.dataset.client === client.value);
        filter(branch, item => centenary
            ? item.dataset.centenary === '1' && item.dataset.region === region.value
            : !item.dataset.centenary && item.dataset.bank === option?.dataset.bank);
    };
    client.addEventListener('change', refresh);
    region.addEventListener('change', refresh);
    refresh();
})();
</script>
