<?php

namespace Database\Seeders;

use App\Models\BillableRate;
use App\Models\CurrencyType;
use Illuminate\Database\Seeder;

class BillableRateSeeder extends Seeder
{
    public function run(): void
    {
        $currencyId = CurrencyType::where('name', 'Uganda Shilling')->value('id');

        foreach ([
            ['name' => 'Partner Hourly Rate', 'hourly_rate' => 350000],
            ['name' => 'Associate Hourly Rate', 'hourly_rate' => 200000],
            ['name' => 'Paralegal Hourly Rate', 'hourly_rate' => 100000],
        ] as $index => $item) {
            BillableRate::firstOrCreate(
                ['name' => $item['name']],
                $item + ['currency_type_id' => $currencyId, 'sort_order' => $index + 1]
            );
        }
    }
}
