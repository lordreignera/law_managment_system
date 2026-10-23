<?php

namespace Database\Seeders;

use App\Models\RecoveryClient;
use App\Support\Recoveries\RecoveryPortfolioProfiles;
use Illuminate\Database\Seeder;

class RecoveryClientSeeder extends Seeder
{
    public function run(): void
    {
        $sortOrder = 0;

        foreach (RecoveryPortfolioProfiles::CLIENT_PORTFOLIOS as $name => $portfolioTypes) {
            $sortOrder++;
            $client = RecoveryClient::firstOrNew(['name' => $name]);
            $client->portfolio_types = array_values(array_unique(array_merge(
                $portfolioTypes,
                $client->portfolio_types ?: []
            )));

            if (! $client->exists) {
                $client->is_active = true;
                $client->sort_order = $sortOrder;
                $client->description = 'Recovery portfolio client. Import account records only after receiving an authorized lender file.';
            }

            $client->save();
        }
    }
}
