<?php

namespace Database\Seeders;

use App\Models\EngagementType;
use Illuminate\Database\Seeder;

class EngagementTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Hourly Billing', 'Fixed Fee', 'Retainer', 'Contingency', 'Pro Bono'] as $index => $name) {
            EngagementType::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
