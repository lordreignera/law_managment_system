<?php

namespace Database\Seeders;

use App\Models\CurrencyType;
use Illuminate\Database\Seeder;

class CurrencyTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Uganda Shilling', 'symbol' => 'UGX'],
            ['name' => 'US Dollar', 'symbol' => '$'],
            ['name' => 'Euro', 'symbol' => 'EUR'],
            ['name' => 'Pound Sterling', 'symbol' => 'GBP'],
        ] as $index => $item) {
            CurrencyType::firstOrCreate(['name' => $item['name']], $item + ['sort_order' => $index + 1]);
        }
    }
}
