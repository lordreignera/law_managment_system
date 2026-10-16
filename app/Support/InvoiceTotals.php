<?php

namespace App\Support;

use App\Models\TaxSetting;
use Illuminate\Validation\Rule;

class InvoiceTotals
{
    public static function rules(): array
    {
        return [
            'tax_setting_id' => ['nullable', Rule::exists('tax_settings', 'id')->where('is_active', true)],
            'currency' => ['required', 'string', Rule::in(['UGX']), ReferenceData::currencyRule()],
            'tax' => ['prohibited'],
        ];
    }

    public static function calculate(array $data): array
    {
        $setting = empty($data['tax_setting_id']) ? null : TaxSetting::findOrFail($data['tax_setting_id']);
        $amount = round((float) $data['subtotal'], 2);
        $rate = $setting ? (float) $setting->rate / 100 : 0;
        $withholding = $setting?->tax_type === 'withholding' ? round($amount * $rate, 2) : 0;
        $subtotal = $setting && $setting->tax_type === 'vat' && $setting->is_inclusive
            ? round($amount / (1 + $rate), 2) : $amount;
        $tax = $setting?->tax_type === 'vat'
            ? ($setting->is_inclusive ? $amount - $subtotal : round($subtotal * $rate, 2)) : 0;

        return [
            'currency' => $data['currency'],
            'tax_setting_id' => $setting?->id,
            'tax_name' => $setting?->name,
            'tax_rate' => $setting?->rate ?? 0,
            'tax_inclusive' => $setting?->is_inclusive ?? false,
            'subtotal' => $subtotal,
            'tax' => round($tax, 2),
            'withholding_tax' => $withholding,
            'total' => round($subtotal + $tax - $withholding, 2),
        ];
    }
}
