<?php

namespace Database\Seeders;

use App\Models\AccountClass;
use App\Models\ChartAccount;
use Illuminate\Database\Seeder;

class ChartAccountSeeder extends Seeder
{
    private array $parents = [];

    public function run(): void
    {
        $classes = AccountClass::pluck('id', 'name');

        foreach ($this->accounts() as $row) {
            $classId = $classes[$row['class']] ?? null;
            if (! $classId) {
                continue;
            }

            $parentId = isset($row['parent']) ? ($this->parents[$row['parent']] ?? null) : null;
            $level = $parentId ? ((int) ChartAccount::find($parentId)?->level + 1) : 1;
            $normalBalance = $row['normal_balance'] ?? match ($row['class']) {
                'Liabilities', 'Equity', 'Income' => 'credit',
                default => 'debit',
            };

            $account = ChartAccount::updateOrCreate(
                ['account_number' => $row['number']],
                [
                    'account_class_id' => $classId,
                    'parent_id' => $parentId,
                    'name' => $row['name'],
                    'account_type' => match ($row['class']) {
                        'Liabilities' => 'liability',
                        'Equity' => 'equity',
                        'Income' => 'income',
                        'Expenses' => 'expense',
                        default => 'asset',
                    },
                    'normal_balance' => $normalBalance,
                    'level' => max($level, 1),
                    'description' => $row['description'] ?? null,
                    'is_postable' => $row['postable'] ?? true,
                    'is_bank_account' => $row['bank'] ?? false,
                    'is_cash_account' => $row['cash'] ?? false,
                    'is_client_funds_account' => $row['client_funds'] ?? false,
                    'currency_code' => $row['currency'] ?? null,
                    'sort_order' => $row['sort_order'] ?? (int) $row['number'],
                    'source_row' => $row['source_row'] ?? null,
                    'source_column' => $row['source_column'] ?? null,
                    'is_active' => true,
                ]
            );

            $this->parents[$row['key'] ?? $row['number']] = $account->id;
        }
    }

    private function accounts(): array
    {
        return [
            ['class' => 'Assets', 'number' => '1100', 'key' => 'fixed_assets', 'name' => 'Fixed Assets', 'postable' => false, 'source_row' => 2, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1110', 'key' => 'computer_printer', 'parent' => 'fixed_assets', 'name' => 'Computer & Printer', 'postable' => false, 'source_row' => 3, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1111', 'parent' => 'computer_printer', 'name' => 'Original Cost', 'source_row' => 4, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1112', 'parent' => 'computer_printer', 'name' => 'Depreciation to Date', 'normal_balance' => 'credit', 'source_row' => 5, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1120', 'key' => 'furniture', 'parent' => 'fixed_assets', 'name' => 'Furniture, Fixtures & Fittings', 'postable' => false, 'source_row' => 6, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1121', 'parent' => 'furniture', 'name' => 'Original Cost', 'source_row' => 7, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1122', 'parent' => 'furniture', 'name' => 'Depreciation to Date', 'normal_balance' => 'credit', 'source_row' => 8, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1130', 'key' => 'library_books', 'parent' => 'fixed_assets', 'name' => 'Library Books', 'postable' => false, 'source_row' => 9, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1131', 'parent' => 'library_books', 'name' => 'Original Cost', 'source_row' => 10, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1132', 'parent' => 'library_books', 'name' => 'Depreciation to Date', 'normal_balance' => 'credit', 'source_row' => 11, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1140', 'key' => 'other_assets', 'parent' => 'fixed_assets', 'name' => 'Other Assets', 'postable' => false, 'source_row' => 12, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1141', 'parent' => 'other_assets', 'name' => 'Original Cost', 'source_row' => 13, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1142', 'parent' => 'other_assets', 'name' => 'Depreciation to Date', 'normal_balance' => 'credit', 'source_row' => 14, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1150', 'key' => 'telecom_equipment', 'parent' => 'fixed_assets', 'name' => 'Telecommunication Equip', 'postable' => false, 'source_row' => 15, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1151', 'parent' => 'telecom_equipment', 'name' => 'Original Cost', 'source_row' => 16, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1152', 'parent' => 'telecom_equipment', 'name' => 'Depreciation to Date', 'normal_balance' => 'credit', 'source_row' => 17, 'source_column' => 'F'],
            ['class' => 'Assets', 'number' => '1200', 'key' => 'tax_assets', 'name' => 'Tax Assets', 'postable' => false],
            ['class' => 'Assets', 'number' => '1210', 'parent' => 'tax_assets', 'name' => 'Provisional Income Tax', 'source_row' => 18, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1220', 'parent' => 'tax_assets', 'name' => 'Withholding Tax Receivable', 'source_row' => 19, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1300', 'key' => 'current_accounts', 'name' => "Current A/C's", 'postable' => false, 'source_row' => 20, 'source_column' => 'B'],
            ['class' => 'Assets', 'number' => '1310', 'key' => 'ugx_bank_accounts', 'parent' => 'current_accounts', 'name' => 'Bank Accounts - Shillings', 'postable' => false, 'source_row' => 21, 'source_column' => 'D'],
            ['class' => 'Assets', 'number' => '1311', 'parent' => 'ugx_bank_accounts', 'name' => 'Stanbic Bank UGX', 'bank' => true, 'currency' => 'UGX', 'source_row' => 22, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1312', 'parent' => 'ugx_bank_accounts', 'name' => 'Equity Bank UGX', 'bank' => true, 'currency' => 'UGX', 'source_row' => 23, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1313', 'parent' => 'ugx_bank_accounts', 'name' => 'Momo UGX', 'cash' => true, 'currency' => 'UGX', 'source_row' => 24, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1314', 'parent' => 'ugx_bank_accounts', 'name' => 'Petty Cash UGX', 'cash' => true, 'currency' => 'UGX', 'source_row' => 25, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1315', 'parent' => 'ugx_bank_accounts', 'name' => 'Cash on Hand UGX', 'cash' => true, 'currency' => 'UGX', 'source_row' => 26, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1320', 'key' => 'usd_bank_accounts', 'parent' => 'current_accounts', 'name' => 'Bank Accounts - Dollars', 'postable' => false, 'source_row' => 27, 'source_column' => 'D'],
            ['class' => 'Assets', 'number' => '1321', 'parent' => 'usd_bank_accounts', 'name' => 'Stanbic Bank USD', 'bank' => true, 'currency' => 'USD', 'source_row' => 28, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1322', 'parent' => 'usd_bank_accounts', 'name' => 'Equity Bank USD', 'bank' => true, 'currency' => 'USD', 'source_row' => 29, 'source_column' => 'E'],
            ['class' => 'Assets', 'number' => '1323', 'parent' => 'usd_bank_accounts', 'name' => 'Cash on Hand USD', 'cash' => true, 'currency' => 'USD', 'source_row' => 30, 'source_column' => 'E'],

            ['class' => 'Liabilities', 'number' => '2100', 'key' => 'current_liabilities', 'name' => 'Current Liabilities', 'postable' => false, 'source_row' => 31, 'source_column' => 'D'],
            ['class' => 'Liabilities', 'number' => '2110', 'parent' => 'current_liabilities', 'name' => 'Clients Accounts With Firm', 'client_funds' => true, 'source_row' => 32, 'source_column' => 'E'],
            ['class' => 'Liabilities', 'number' => '2120', 'parent' => 'current_liabilities', 'name' => 'Disbursements Accounts with Firm', 'client_funds' => true, 'source_row' => 33, 'source_column' => 'E'],
            ['class' => 'Liabilities', 'number' => '2130', 'parent' => 'current_liabilities', 'name' => 'Audit Fees Payable', 'source_row' => 34, 'source_column' => 'E'],
            ['class' => 'Liabilities', 'number' => '2140', 'key' => 'payroll_payable', 'parent' => 'current_liabilities', 'name' => 'Payroll Payable Account', 'postable' => false, 'source_row' => 35, 'source_column' => 'E'],
            ['class' => 'Liabilities', 'number' => '2141', 'parent' => 'payroll_payable', 'name' => 'PAYE Payable', 'source_row' => 36, 'source_column' => 'F'],
            ['class' => 'Liabilities', 'number' => '2142', 'parent' => 'payroll_payable', 'name' => 'NSSF Payable', 'source_row' => 37, 'source_column' => 'F'],
            ['class' => 'Liabilities', 'number' => '2143', 'parent' => 'payroll_payable', 'name' => 'Taxes payable', 'source_row' => 38, 'source_column' => 'F'],
            ['class' => 'Liabilities', 'number' => '2150', 'parent' => 'current_liabilities', 'name' => 'VAT Control', 'source_row' => 39, 'source_column' => 'E'],
            ['class' => 'Liabilities', 'number' => '2160', 'parent' => 'current_liabilities', 'name' => 'Withholding Tax', 'source_row' => 40, 'source_column' => 'E'],

            ['class' => 'Equity', 'number' => '3100', 'name' => 'Partners Capital A/C', 'source_row' => 41, 'source_column' => 'C'],
            ['class' => 'Equity', 'number' => '3200', 'name' => 'Partners Current A/C B/F', 'source_row' => 42, 'source_column' => 'C'],

            ['class' => 'Income', 'number' => '4000', 'name' => 'Income', 'source_row' => 43, 'source_column' => 'B'],

            ['class' => 'Expenses', 'number' => '5000', 'key' => 'expenses', 'name' => 'Expenses', 'postable' => false],
            ['class' => 'Expenses', 'number' => '5100', 'key' => 'dues_subscriptions', 'parent' => 'expenses', 'name' => 'Dues & Subscriptions', 'postable' => false, 'source_row' => 44, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5101', 'parent' => 'dues_subscriptions', 'name' => 'ABC Subscription', 'source_row' => 45, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5102', 'parent' => 'dues_subscriptions', 'name' => 'Subscriptions - Others', 'source_row' => 46, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5200', 'key' => 'employment_costs', 'parent' => 'expenses', 'name' => 'Employment Costs', 'postable' => false, 'source_row' => 47, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5201', 'parent' => 'employment_costs', 'name' => 'PAYE Payable', 'source_row' => 48, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5202', 'parent' => 'employment_costs', 'name' => 'NSSF Company Contributions 10%', 'source_row' => 49, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5203', 'parent' => 'employment_costs', 'name' => 'Local Service Tax', 'source_row' => 50, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5204', 'parent' => 'employment_costs', 'name' => 'Partners Salaries', 'source_row' => 51, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5205', 'parent' => 'employment_costs', 'name' => 'Staff Salary', 'source_row' => 52, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5206', 'parent' => 'employment_costs', 'name' => '5% NSSF Employee Contributions', 'source_row' => 53, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5207', 'parent' => 'employment_costs', 'name' => 'Medical', 'source_row' => 54, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5208', 'parent' => 'employment_costs', 'name' => 'Staff Lunch', 'source_row' => 55, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5209', 'parent' => 'employment_costs', 'name' => 'Casuals/Temporary Help', 'source_row' => 56, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5210', 'parent' => 'employment_costs', 'name' => 'Practicing Certificate', 'source_row' => 57, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5211', 'parent' => 'employment_costs', 'name' => 'Staff Welfare', 'source_row' => 58, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5212', 'parent' => 'employment_costs', 'name' => 'Training & Retreat Costs', 'source_row' => 59, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5300', 'key' => 'financial_costs', 'parent' => 'expenses', 'name' => 'Financial Costs', 'postable' => false, 'source_row' => 60, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5301', 'parent' => 'financial_costs', 'name' => 'Bank Charges', 'source_row' => 61, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5302', 'parent' => 'financial_costs', 'name' => 'Interest', 'source_row' => 62, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5303', 'parent' => 'financial_costs', 'name' => 'Professional Negligence', 'source_row' => 63, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5304', 'parent' => 'financial_costs', 'name' => 'Depreciation', 'source_row' => 64, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5305', 'parent' => 'financial_costs', 'name' => 'Disbursement', 'source_row' => 65, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5306', 'parent' => 'financial_costs', 'name' => 'Insurance', 'source_row' => 66, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5400', 'key' => 'office_costs', 'parent' => 'expenses', 'name' => 'Office Costs', 'postable' => false, 'source_row' => 67, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5401', 'parent' => 'office_costs', 'name' => 'Printer & Fax Cartridge/Toner', 'source_row' => 68, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5402', 'parent' => 'office_costs', 'name' => 'DSTV Subscription', 'source_row' => 69, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5403', 'parent' => 'office_costs', 'name' => 'Donations', 'source_row' => 70, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5404', 'parent' => 'office_costs', 'name' => 'Advertisement', 'source_row' => 71, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5405', 'parent' => 'office_costs', 'name' => 'Library Expenses', 'source_row' => 72, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5406', 'parent' => 'office_costs', 'name' => 'Newspapers', 'source_row' => 73, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5407', 'parent' => 'office_costs', 'name' => 'Other Office Costs', 'source_row' => 74, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5408', 'parent' => 'office_costs', 'name' => 'Archiving Costs', 'source_row' => 75, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5409', 'parent' => 'office_costs', 'name' => 'Security', 'source_row' => 76, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5410', 'parent' => 'office_costs', 'name' => 'Stationery - General', 'source_row' => 77, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5411', 'parent' => 'office_costs', 'name' => 'Computers/Fax/Printer', 'source_row' => 78, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5500', 'key' => 'professional_services', 'parent' => 'expenses', 'name' => 'Professional Services', 'postable' => false, 'source_row' => 79, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5501', 'parent' => 'professional_services', 'name' => 'Audit Fees', 'source_row' => 80, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5502', 'parent' => 'professional_services', 'name' => 'Accounting Fees', 'source_row' => 81, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5503', 'parent' => 'professional_services', 'name' => 'Legal Fees', 'source_row' => 82, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5504', 'parent' => 'professional_services', 'name' => 'Consultancy Fees', 'source_row' => 83, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5600', 'key' => 'rent', 'parent' => 'expenses', 'name' => 'Rent', 'postable' => false, 'source_row' => 84, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5601', 'parent' => 'rent', 'name' => 'Rent Office', 'source_row' => 85, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5602', 'parent' => 'rent', 'name' => 'Rent Other', 'source_row' => 86, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5700', 'key' => 'repairs', 'parent' => 'expenses', 'name' => 'Repairs/Maintenance', 'postable' => false, 'source_row' => 87, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5701', 'parent' => 'repairs', 'name' => 'Air Conditioners', 'source_row' => 88, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5702', 'parent' => 'repairs', 'name' => 'Cleaning Requirements', 'source_row' => 89, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5703', 'parent' => 'repairs', 'name' => 'Office Renovation', 'source_row' => 90, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5704', 'parent' => 'repairs', 'name' => 'General Office Maintenance', 'source_row' => 91, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5705', 'parent' => 'repairs', 'name' => 'Computer & Printer Repairs', 'source_row' => 92, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5800', 'key' => 'taxes', 'parent' => 'expenses', 'name' => 'Taxes', 'postable' => false, 'source_row' => 93, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5801', 'parent' => 'taxes', 'name' => 'Income Tax', 'source_row' => 94, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5802', 'parent' => 'taxes', 'name' => 'Other Taxes', 'source_row' => 95, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5900', 'key' => 'telecommunications', 'parent' => 'expenses', 'name' => 'Telecommunications', 'postable' => false, 'source_row' => 96, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5901', 'parent' => 'telecommunications', 'name' => 'Email/Internet Costs', 'source_row' => 97, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5902', 'parent' => 'telecommunications', 'name' => 'Postage & Courier', 'source_row' => 98, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5903', 'parent' => 'telecommunications', 'name' => 'Phone & Fax', 'source_row' => 99, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '5904', 'parent' => 'telecommunications', 'name' => 'Mobile Phone Bills', 'source_row' => 100, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6000', 'key' => 'transport', 'parent' => 'expenses', 'name' => 'Transport Costs', 'postable' => false, 'source_row' => 101, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6001', 'parent' => 'transport', 'name' => 'M/Cycle Fuel/Oil/Maintenance', 'source_row' => 102, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6002', 'parent' => 'transport', 'name' => 'Motor Cycle Repairs', 'source_row' => 103, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6003', 'parent' => 'transport', 'name' => 'Other Transport Costs', 'source_row' => 104, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6004', 'parent' => 'transport', 'name' => 'Parking Expenses', 'source_row' => 105, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6100', 'key' => 'utilities', 'parent' => 'expenses', 'name' => 'Utilities', 'postable' => false, 'source_row' => 106, 'source_column' => 'D'],
            ['class' => 'Expenses', 'number' => '6101', 'parent' => 'utilities', 'name' => 'Electricity', 'source_row' => 107, 'source_column' => 'D'],
        ];
    }
}
