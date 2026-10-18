<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankBranch;
use Illuminate\Database\Seeder;

class BankBranchSeeder extends Seeder
{
    public function run(): void
    {
        $banks = Bank::query()->get()->keyBy('name');

        foreach ($this->branches() as $bankName => $branches) {
            $bank = $banks->get($bankName);

            if (! $bank) {
                continue;
            }

            foreach ($branches as $index => $branch) {
                BankBranch::updateOrCreate(
                    ['bank_id' => $bank->id, 'name' => $branch['name']],
                    [
                        'office_location' => $branch['office_location'],
                        'description' => $branch['description'],
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * These are the initial locations needed by the firm's Kampala, Mbale and
     * Mbarara work. The official bank locator or annual report is the source
     * for the named locations; administrators can add the wider network from
     * Settings > Banks and Bank branches or the import template.
     */
    private function branches(): array
    {
        $locations = [
            'Kampala' => 'Kampala',
            'Mbale' => 'Mbale',
            'Mbarara' => 'Mbarara',
        ];

        $standard = function (array $names) use ($locations): array {
            return collect($locations)->map(fn (string $city) => [
                'name' => $names[$city],
                'office_location' => $city,
                'description' => 'Initial Uganda branch seed; confirm the current service address before using it for correspondence.',
            ])->values()->all();
        };

        return [
            'Absa Bank Uganda' => $standard(['Kampala' => 'Kampala Road Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Bank of Africa Uganda' => $standard(['Kampala' => 'Kampala Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Centenary Bank' => $standard(['Kampala' => 'Kampala Road Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'dfcu Bank' => $standard(['Kampala' => 'Kampala Road Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Diamond Trust Bank Uganda' => $standard(['Kampala' => 'Kampala Road Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Equity Bank Uganda' => $standard(['Kampala' => 'Church House Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Exim Bank Uganda' => $standard(['Kampala' => 'Kampala Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Housing Finance Bank' => $standard(['Kampala' => 'Head Office Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'KCB Bank Uganda' => $standard(['Kampala' => 'Kampala Road Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'NCBA Bank Uganda' => $standard(['Kampala' => 'Kampala Branch', 'Mbale' => 'Mbale Branch', 'Mbarara' => 'Mbarara Branch']),
            'Stanbic Bank Uganda' => [
                ['name' => 'Corporate Branch', 'office_location' => 'Kampala', 'description' => 'Official Stanbic branch locator location.'],
                ['name' => 'Lugogo Branch', 'office_location' => 'Kampala', 'description' => 'Official Stanbic branch locator location.'],
                ['name' => 'Garden City Branch', 'office_location' => 'Kampala', 'description' => 'Official Stanbic branch locator location.'],
                ['name' => 'Metro Branch', 'office_location' => 'Kampala', 'description' => 'Official Stanbic branch locator location.'],
                ['name' => 'Wandegeya Branch', 'office_location' => 'Kampala', 'description' => 'Official Stanbic branch locator location.'],
                ['name' => 'Mbale Branch', 'office_location' => 'Mbale', 'description' => 'Initial Uganda branch seed; confirm the current service address before using it for correspondence.'],
                ['name' => 'Mbarara Branch', 'office_location' => 'Mbarara', 'description' => 'Initial Uganda branch seed; confirm the current service address before using it for correspondence.'],
            ],
        ];
    }
}
