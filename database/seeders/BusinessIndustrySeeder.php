<?php

namespace Database\Seeders;

use App\Models\BusinessIndustry;
use Illuminate\Database\Seeder;

class BusinessIndustrySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Banking and Finance', 'Real Estate', 'Government', 'Energy', 'Insurance', 'Telecommunications', 'Manufacturing', 'Non Profit'] as $index => $name) {
            BusinessIndustry::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
