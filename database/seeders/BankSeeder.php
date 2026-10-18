<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->banks() as $index => $bankName) {
            Bank::updateOrCreate(
                ['name' => $bankName],
                [
                    'description' => 'Uganda Bankers Association member master data; confirm current products and service locations before production use.',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Uganda's commercial-bank master list is seeded from the Uganda Bankers
     * Association member list. Branch locations are kept in BankBranchSeeder
     * so that a bank can be maintained independently from its locations.
     */
    private function banks(): array
    {
        return [
            'Absa Bank Uganda',
            'ABC Capital Bank',
            'Bank of Africa Uganda',
            'Bank of Baroda Uganda',
            'Bank of India Uganda',
            'Cairo Bank Uganda',
            'Centenary Bank',
            'Citibank Uganda',
            'dfcu Bank',
            'Diamond Trust Bank Uganda',
            'East African Development Bank',
            'Ecobank Uganda',
            'Equity Bank Uganda',
            'Exim Bank Uganda',
            'Finance Trust Bank',
            'FINCA Uganda',
            'Guaranty Trust Bank Uganda',
            'Housing Finance Bank',
            'I&M Bank Uganda',
            'KCB Bank Uganda',
            'NCBA Bank Uganda',
            'Opportunity Bank Uganda',
            'Pearl Bank Uganda',
            'Pride Microfinance',
            'Salaam Bank Uganda',
            'Stanbic Bank Uganda',
            'Standard Chartered Bank Uganda',
            'Tropical Bank Uganda',
            'Uganda Development Bank',
            'United Bank for Africa Uganda',
            'Yako Microfinance',
            'UGAFODE',
            'BRAC Uganda',
            'Afreximbank',
        ];
    }
}
