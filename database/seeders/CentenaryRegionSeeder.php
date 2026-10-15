<?php

namespace Database\Seeders;

use App\Models\CentenaryBranch;
use App\Models\CentenaryRegion;
use Illuminate\Database\Seeder;

class CentenaryRegionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Central' => ['Kampala', 'Ntinda', 'Entebbe'],
            'Eastern' => ['Jinja', 'Mbale'],
            'Western' => ['Mbarara', 'Fort Portal'],
            'Northern' => ['Gulu', 'Lira'],
        ] as $regionName => $branches) {
            $region = CentenaryRegion::firstOrCreate(['name' => $regionName]);

            foreach ($branches as $branchName) {
                CentenaryBranch::firstOrCreate([
                    'centenary_region_id' => $region->id,
                    'name' => $branchName,
                ], [
                    'office_location' => $branchName,
                ]);
            }
        }
    }
}
