<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Annual Leave', 'Sick Leave', 'Study Leave', 'Maternity Leave', 'Paternity Leave', 'Compassionate Leave'] as $index => $name) {
            LeaveType::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
