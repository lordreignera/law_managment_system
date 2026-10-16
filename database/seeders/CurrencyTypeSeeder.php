<?php

namespace Database\Seeders;

use App\Models\CurrencyType;
use Illuminate\Database\Seeder;

class CurrencyTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Uganda Shilling', 'iso_code' => 'UGX', 'symbol' => 'UGX'],
            ['name' => 'US Dollar', 'iso_code' => 'USD', 'symbol' => '$'],
            ['name' => 'Euro', 'iso_code' => 'EUR', 'symbol' => 'EUR'],
            ['name' => 'Pound Sterling', 'iso_code' => 'GBP', 'symbol' => 'GBP'],
        ] as $index => $item) {
            CurrencyType::firstOrCreate(['name' => $item['name']], $item + ['sort_order' => $index + 1]);
        }
    }
}
