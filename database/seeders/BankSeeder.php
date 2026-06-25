<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Stanbic Bank', 'DFCU Bank', 'Bank of Africa', 'Uganda Development Bank', 'Centenary Bank'] as $index => $name) {
            Bank::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
