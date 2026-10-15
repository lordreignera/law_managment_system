<?php

namespace Database\Seeders;

use App\Models\OpposingCounsel;
use Illuminate\Database\Seeder;

class OpposingCounselSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Office of the Solicitor General', 'firm_name' => 'Government of Uganda'],
            ['name' => 'MMAKS Advocates', 'firm_name' => 'MMAKS Advocates'],
            ['name' => 'S&L Advocates', 'firm_name' => 'S&L Advocates'],
        ] as $index => $counsel) {
            OpposingCounsel::firstOrCreate(
                ['name' => $counsel['name'], 'firm_name' => $counsel['firm_name']],
                ['sort_order' => $index + 1]
            );
        }
    }
}
