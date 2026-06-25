<?php

namespace Database\Seeders;

use App\Models\BudgetCategory;
use Illuminate\Database\Seeder;

class BudgetCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Court Fees', 'Transport', 'Filing Fees', 'Professional Fees', 'Office Operations', 'Client Disbursements'] as $index => $name) {
            BudgetCategory::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
