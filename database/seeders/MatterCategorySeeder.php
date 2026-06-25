<?php

namespace Database\Seeders;

use App\Models\MatterCategory;
use Illuminate\Database\Seeder;

class MatterCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Civil Matter', 'Commercial Matter', 'Criminal Matter', 'Conveyancing', 'Debt Recovery', 'Advisory', 'Employment Matter'] as $index => $name) {
            MatterCategory::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
