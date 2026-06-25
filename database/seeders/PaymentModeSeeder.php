<?php

namespace Database\Seeders;

use App\Models\PaymentMode;
use Illuminate\Database\Seeder;

class PaymentModeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Cash', 'Bank Transfer', 'Cheque', 'Mobile Money', 'Card Payment'] as $index => $name) {
            PaymentMode::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
