<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Letterhead;
use Illuminate\Database\Seeder;

class LetterheadSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanySetting::current();

        Letterhead::firstOrCreate(
            ['name' => 'Default Firm Letterhead'],
            [
                'header_text' => $company->company_name,
                'footer_text' => trim(($company->contact_email ?: '').' '.($company->contact_phone ?: '')),
                'description' => 'Default letterhead used for firm correspondence.',
                'is_default' => true,
                'sort_order' => 1,
            ]
        );
    }
}
