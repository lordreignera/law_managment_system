<label>
    <span>Invoice Currency</span>
    <input type="text" name="currency" value="UGX" readonly>
    <small>Invoices and their payments use the firm's UGX reporting currency.</small>
    @error('currency') <small>{{ $message }}</small> @enderror
</label>
<label>
    <span>Tax Treatment</span>
    <select name="tax_setting_id">
        <option value="">No tax</option>
        @foreach (\App\Models\TaxSetting::where('is_active', true)->orderBy('sort_order')->get() as $taxSetting)
            <option value="{{ $taxSetting->id }}" @selected((string) old('tax_setting_id') === (string) $taxSetting->id)>{{ $taxSetting->name }} — {{ (float) $taxSetting->rate }}% {{ $taxSetting->tax_type === 'withholding' ? '(deducted)' : ($taxSetting->is_inclusive ? '(included)' : '(added)') }}</option>
        @endforeach
    </select>
    <small>Tax is calculated when saved. For inclusive tax, enter the amount including tax in Subtotal.</small>
    @error('tax_setting_id') <small>{{ $message }}</small> @enderror
    @error('tax') <small>{{ $message }}</small> @enderror
</label>
