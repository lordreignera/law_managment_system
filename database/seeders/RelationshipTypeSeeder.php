<?php

namespace Database\Seeders;

use App\Models\RelationshipType;
use Illuminate\Database\Seeder;

class RelationshipTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Spouse', 'Parent', 'Child', 'Sibling', 'Guardian', 'Relative', 'Business Partner', 'Employer', 'Employee', 'Other'] as $index => $name) {
            RelationshipType::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
