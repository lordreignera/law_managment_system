<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Filing Expense', 'Transport Expense', 'Communication', 'Stationery', 'Search Fees', 'Professional Disbursement'] as $index => $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
