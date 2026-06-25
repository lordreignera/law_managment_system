<?php

namespace Database\Seeders;

use App\Models\Court;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Supreme Court of Uganda', 'court_level' => 'Supreme Court', 'station' => 'Kampala'],
            ['name' => 'Court of Appeal of Uganda', 'court_level' => 'Court of Appeal', 'station' => 'Kampala'],
            ['name' => 'High Court Commercial Division', 'court_level' => 'High Court', 'station' => 'Kampala'],
            ['name' => 'High Court Land Division', 'court_level' => 'High Court', 'station' => 'Kampala'],
            ['name' => 'Chief Magistrates Court Kampala', 'court_level' => 'Magistrates Court', 'station' => 'Kampala'],
        ] as $index => $item) {
            Court::firstOrCreate(['name' => $item['name']], $item + ['sort_order' => $index + 1]);
        }
    }
}
