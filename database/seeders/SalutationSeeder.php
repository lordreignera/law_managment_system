<?php

namespace Database\Seeders;

use App\Models\Salutation;
use Illuminate\Database\Seeder;

class SalutationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof', 'Hon', 'Rev', 'Capt', 'Counsel'] as $index => $name) {
            Salutation::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
