<?php

namespace Database\Seeders;

use App\Models\ChartAccount;
use App\Models\FinanceAccountMapping;
use Illuminate\Database\Seeder;

class FinanceAccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['module' => 'billing', 'mapping_key' => 'invoice_income', 'account' => '4000', 'notes' => 'Default income account for client invoices.'],
            ['module' => 'payments', 'mapping_key' => 'default_ugx_bank', 'account' => '1311', 'notes' => 'Default UGX bank account.'],
            ['module' => 'payments', 'mapping_key' => 'default_usd_bank', 'account' => '1321', 'notes' => 'Default USD bank account.'],
            ['module' => 'petty_cash', 'mapping_key' => 'cash_account', 'account' => '1314', 'notes' => 'Default petty cash account.'],
            ['module' => 'client_funds', 'mapping_key' => 'client_money_liability', 'account' => '2110', 'notes' => 'Client money held by the firm.'],
            ['module' => 'client_funds', 'mapping_key' => 'disbursements_liability', 'account' => '2120', 'notes' => 'Client disbursement money held by the firm.'],
            ['module' => 'tax', 'mapping_key' => 'vat_control', 'account' => '2150', 'notes' => 'VAT control account.'],
            ['module' => 'tax', 'mapping_key' => 'withholding_tax_payable', 'account' => '2160', 'notes' => 'Withholding tax payable account.'],
            ['module' => 'payroll', 'mapping_key' => 'payroll_payable', 'account' => '2140', 'notes' => 'Payroll payable control account.'],
        ] as $row) {
            $account = ChartAccount::where('account_number', $row['account'])->first();

            if (! $account) {
                continue;
            }

            FinanceAccountMapping::updateOrCreate(
                ['module' => $row['module'], 'mapping_key' => $row['mapping_key']],
                ['chart_account_id' => $account->id, 'notes' => $row['notes']]
            );
        }
    }
}
