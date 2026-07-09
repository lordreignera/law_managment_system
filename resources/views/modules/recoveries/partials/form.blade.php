@php $account = $account ?? null; @endphp

<div class="kfms-form-grid">
    <label>
        <span>Bank / Client</span>
        <select name="recovery_client_id" required>
            <option value="">Select bank/client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected(old('recovery_client_id', $account?->recovery_client_id) == $client->id)>{{ $client->name }}</option>
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
        <span>Portfolio Type</span>
        <input type="text" name="portfolio_type" value="{{ old('portfolio_type', $account?->portfolio_type) }}" maxlength="100" placeholder="NPL, Write Off, BOA, UDB">
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
        <span>Phone</span>
        <input type="text" name="phone" value="{{ old('phone', $account?->phone) }}" maxlength="50">
        @error('phone') <small>{{ $message }}</small> @enderror
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
        <span>Region</span>
        <input type="text" name="region" value="{{ old('region', $account?->region) }}" maxlength="191">
        @error('region') <small>{{ $message }}</small> @enderror
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
        <input type="text" name="currency" value="{{ old('currency', $account?->currency ?? 'UGX') }}" maxlength="10">
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
</div>
