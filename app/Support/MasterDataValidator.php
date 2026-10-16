<?php

namespace App\Support;

use App\Support\Notifications\NotificationCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterDataValidator
{
    public function validate(array $input, array $definition, ?Model $record = null): array
    {
        $table = (new $definition['model'])->getTable();
        $fields = $definition['extra_fields'] ?? [];
        $nameRule = Rule::unique($table, 'name')->ignore($record?->id);
        foreach (['court_id', 'bank_id', 'centenary_region_id'] as $parent) {
            if (in_array($parent, $fields, true)) {
                $nameRule->where($parent, $input[$parent] ?? null);
            }
        }
        $rules = [
            'name' => ['required', 'string', 'max:'.($table === 'opposing_counsels' ? 120 : 191), $nameRule],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
        $extraRules = [
            'court_level' => ['nullable', 'string', 'max:120'],
            'station' => ['nullable', 'string', 'max:120'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'iso_code' => ['required', 'string', 'regex:/^[A-Z]{3}$/', Rule::unique($table, 'iso_code')->ignore($record?->id)],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'firm_name' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:191'],
            'chambers' => ['nullable', 'string', 'max:191'],
            'court_id' => ['nullable', 'exists:courts,id'],
            'centenary_region_id' => ['required', 'exists:centenary_regions,id'],
            'portfolio_types' => ['nullable', 'string', 'max:3000'],
            'header_text' => ['nullable', 'string', 'max:2000'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['nullable', 'boolean'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'currency_type_id' => ['nullable', 'exists:currency_types,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'bank_id' => ['required', 'exists:banks,id'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'districts_covered' => ['nullable', 'string', 'max:3000'],
            'tax_type' => ['required', Rule::in(['vat', 'withholding'])],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_inclusive' => ['nullable', 'boolean'],
            'channel' => ['required', Rule::in(['database', 'email', 'sms'])],
            'event_key' => ['required', Rule::in(array_keys(NotificationCatalog::definitions())), Rule::unique($table, 'event_key')->ignore($record?->id)],
            'subject' => ['nullable', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
        ];
        foreach ($fields as $field) {
            $rules[$field] = $extraRules[$field];
        }
        if (isset($input['iso_code'])) {
            $input['iso_code'] = strtoupper(trim($input['iso_code']));
        }
        $data = Validator::make($input, $rules)->validate();
        $data['sort_order'] = $data['sort_order'] ?? 0;
        foreach (array_intersect(['is_active', 'is_default', 'is_inclusive'], array_keys($rules)) as $field) {
            $data[$field] = filter_var($data[$field] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        if (in_array('portfolio_types', $fields, true)) {
            $data['portfolio_types'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['portfolio_types'] ?? '')))
                ->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
        }
        if (($data['tax_type'] ?? null) === 'withholding') {
            $data['is_inclusive'] = false;
        }
        if (isset($data['event_key'])) {
            $allowed = array_merge(['recipient_name', 'company_name'], NotificationCatalog::definitions()[$data['event_key']]['placeholders']);
            foreach (['subject', 'body'] as $field) {
                preg_match_all('/{{\s*([^{}]+?)\s*}}/', $data[$field] ?? '', $matches);
                if (array_diff(array_map('trim', $matches[1]), $allowed)) {
                    throw ValidationException::withMessages([$field => 'Use only the variables supported by the selected notification event.']);
                }
            }
        }

        return $data;
    }
}
