<?php

namespace Database\Seeders;

use App\Models\ContactPosition;
use Illuminate\Database\Seeder;

class ContactPositionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Director', 'Primary Contact', 'Normal Contact', 'Company Secretary', 'Authorized Signatory', 'Other'] as $index => $name) {
            ContactPosition::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
