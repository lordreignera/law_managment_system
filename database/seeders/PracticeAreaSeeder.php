<?php

namespace Database\Seeders;

use App\Models\PracticeArea;
use Illuminate\Database\Seeder;

class PracticeAreaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Litigation', 'description' => 'Court disputes, hearings, and case management.'],
            ['name' => 'Debt Recoveries', 'description' => 'Bank and institutional recovery work.'],
            ['name' => 'Securities and Land Titles', 'description' => 'Mortgage, title, and securities documentation.'],
            ['name' => 'ESG and Advisory', 'description' => 'Advisory, governance, and compliance services.'],
            ['name' => 'General Corporate', 'description' => 'Commercial and company advisory work.'],
        ] as $index => $item) {
            PracticeArea::firstOrCreate(
                ['name' => $item['name']],
                ['description' => $item['description'], 'sort_order' => $index + 1]
            );
        }
    }
}
