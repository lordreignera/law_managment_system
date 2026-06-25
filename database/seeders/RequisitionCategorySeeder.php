<?php

namespace Database\Seeders;

use App\Models\RequisitionCategory;
use Illuminate\Database\Seeder;

class RequisitionCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Court Filing', 'Transport Request', 'Office Purchase', 'Client Disbursement', 'Process Server', 'Title Search'] as $index => $name) {
            RequisitionCategory::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
