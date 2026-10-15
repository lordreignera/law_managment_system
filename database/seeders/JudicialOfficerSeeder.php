<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\JudicialOfficer;
use Illuminate\Database\Seeder;

class JudicialOfficerSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Chief Justice', 'title' => 'Chief Justice', 'court' => 'Supreme Court of Uganda'],
            ['name' => 'Head of Commercial Division', 'title' => 'Judge', 'court' => 'High Court Commercial Division'],
            ['name' => 'Head of Land Division', 'title' => 'Judge', 'court' => 'High Court Land Division'],
            ['name' => 'Chief Magistrate Kampala', 'title' => 'Chief Magistrate', 'court' => 'Chief Magistrates Court Kampala'],
        ] as $index => $item) {
            $courtId = Court::where('name', $item['court'])->value('id');
            JudicialOfficer::firstOrCreate(
                ['name' => $item['name'], 'court_id' => $courtId],
                ['title' => $item['title'], 'sort_order' => $index + 1]
            );
        }
    }
}
