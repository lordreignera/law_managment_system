<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Managing Partner', 'Senior Partner', 'Department Manager', 'Litigation Officer', 'Advocate', 'Legal Associate', 'Paralegal', 'Recoveries Manager', 'Recovery Officer', 'Securities Manager', 'Accountant', 'HR Manager', 'Front Desk Officer', 'IT Manager', 'Administrator', 'System Administrator'] as $index => $name) {
            JobTitle::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
