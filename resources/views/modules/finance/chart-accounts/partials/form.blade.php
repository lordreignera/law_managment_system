<div class="kfms-form-grid">
    <label>
        <span>Account Class *</span>
        <select name="account_class_id" required>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected((string) old('account_class_id', $account->account_class_id) === (string) $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        @error('account_class_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Parent Account</span>
        <select name="parent_id">
            <option value="">No parent / top-level</option>
            @foreach ($parents as $parent)
                @continue($account->exists && $parent->id === $account->id)
                <option value="{{ $parent->id }}" @selected((string) old('parent_id', $account->parent_id) === (string) $parent->id)>
                    {{ $parent->account_number }} - {{ $parent->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Account Number *</span>
        <input type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" required>
        @error('account_number') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Name *</span>
        <input type="text" name="name" value="{{ old('name', $account->name) }}" required maxlength="191">
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Account Type *</span>
        <select name="account_type" required>
            @foreach ($accountTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('account_type', $account->account_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('account_type') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Normal Balance *</span>
        <select name="normal_balance" required>
            @foreach ($normalBalances as $value => $label)
                <option value="{{ $value }}" @selected(old('normal_balance', $account->normal_balance) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('normal_balance') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Currency Code</span>
        <input type="text" name="currency_code" value="{{ old('currency_code', $account->currency_code) }}" maxlength="10" placeholder="UGX, USD">
        @error('currency_code') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Sort Order</span>
        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $account->sort_order) }}">
        @error('sort_order') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Description</span>
        <textarea name="description" rows="3">{{ old('description', $account->description) }}</textarea>
        @error('description') <small>{{ $message }}</small> @enderror
    </label>

    <div class="kfms-span-2 kfms-checkbox-grid">
        <label class="kfms-checkbox">
            <input type="checkbox" name="is_postable" value="1" @checked(old('is_postable', $account->is_postable ?? true))>
            <span>Postable account</span>
        </label>
        <label class="kfms-checkbox">
            <input type="checkbox" name="is_bank_account" value="1" @checked(old('is_bank_account', $account->is_bank_account))>
            <span>Bank account</span>
        </label>
        <label class="kfms-checkbox">
            <input type="checkbox" name="is_cash_account" value="1" @checked(old('is_cash_account', $account->is_cash_account))>
            <span>Cash account</span>
        </label>
        <label class="kfms-checkbox">
            <input type="checkbox" name="is_client_funds_account" value="1" @checked(old('is_client_funds_account', $account->is_client_funds_account))>
            <span>Client funds account</span>
        </label>
        <label class="kfms-checkbox">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active ?? true))>
            <span>Active</span>
        </label>
    </div>
</div>
