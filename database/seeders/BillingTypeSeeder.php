<?php

namespace Database\Seeders;

use App\Models\BillingType;
use Illuminate\Database\Seeder;

class BillingTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Hourly Billing', 'Fixed Fee', 'Retainer', 'Contingency', 'Pro Bono'] as $index => $name) {
            BillingType::firstOrCreate(
                ['name' => $name],
                ['code' => str($name)->slug('_')->toString(), 'sort_order' => $index + 1]
            );
        }
    }
}
