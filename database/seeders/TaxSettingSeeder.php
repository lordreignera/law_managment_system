<?php

namespace Database\Seeders;

use App\Models\TaxSetting;
use Illuminate\Database\Seeder;

class TaxSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Value Added Tax', 'tax_type' => 'vat', 'rate' => 18, 'is_inclusive' => false],
            ['name' => 'Withholding Tax', 'tax_type' => 'withholding', 'rate' => 6, 'is_inclusive' => false],
        ] as $index => $tax) {
            TaxSetting::firstOrCreate(['name' => $tax['name']], $tax + ['sort_order' => $index + 1, 'is_active' => false, 'description' => 'Confirm the rate and treatment with the firm before activating this tax.']);
        }
    }
}
