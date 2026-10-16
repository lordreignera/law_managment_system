<?php

namespace App\Support\Recoveries;

use App\Models\Bank;
use App\Models\CentenaryRegion;
use App\Models\RecoveryClient;
use App\Support\ReferenceData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecoveryReferenceData
{
    public static function validate(array $data, RecoveryClient $client): array
    {
        $data['currency'] = strtoupper(trim($data['currency'] ?? 'UGX'));
        $rules = [
            'portfolio_type' => ['nullable', Rule::in(array_keys($client->portfolioOptions()))],
            'currency' => ['required', ReferenceData::currencyRule()],
        ];
        if (str($client->name)->contains('Centenary', true)) {
            $regionId = CentenaryRegion::where('name', $data['region'] ?? '')->where('is_active', true)->value('id');
            $rules['region'] = ['nullable', 'required_with:branch_name', Rule::exists('centenary_regions', 'name')->where('is_active', true)];
            $rules['branch_name'] = ['nullable', Rule::exists('centenary_branches', 'name')->where('centenary_region_id', $regionId)->where('is_active', true)];
        } elseif (! empty($data['branch_name'])) {
            $bankId = Bank::where('name', $client->name)->where('is_active', true)->value('id');
            $rules['branch_name'] = ['nullable', Rule::exists('bank_branches', 'name')->where('bank_id', $bankId)->where('is_active', true)];
        }
        Validator::make($data, $rules, [
            'portfolio_type.in' => 'Select a portfolio configured for the selected recovery client.',
            'branch_name.exists' => 'Select an active branch belonging to the selected bank and region.',
            'region.exists' => 'Select an active Centenary region.',
        ])->validate();

        return $data;
    }
}
