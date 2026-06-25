<?php

namespace Database\Seeders;

use App\Models\Shelf;
use Illuminate\Database\Seeder;

class ShelfSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Open Files', 'Pending Instructions', 'Court Matters', 'Recoveries', 'Closed Files', 'Archive'] as $index => $name) {
            Shelf::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
