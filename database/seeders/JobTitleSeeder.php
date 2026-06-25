<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Managing Partner', 'Senior Partner', 'Advocate', 'Legal Associate', 'Paralegal', 'Recovery Officer', 'Accountant', 'HR Manager', 'Front Desk Officer'] as $index => $name) {
            JobTitle::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
