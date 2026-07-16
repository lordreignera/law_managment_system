<?php

namespace Database\Seeders;

use App\Models\AccountClass;
use Illuminate\Database\Seeder;

class AccountClassSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Assets', 'code_prefix' => '1', 'normal_balance' => 'debit', 'sort_order' => 10],
            ['name' => 'Liabilities', 'code_prefix' => '2', 'normal_balance' => 'credit', 'sort_order' => 20],
            ['name' => 'Equity', 'code_prefix' => '3', 'normal_balance' => 'credit', 'sort_order' => 30],
            ['name' => 'Income', 'code_prefix' => '4', 'normal_balance' => 'credit', 'sort_order' => 40],
            ['name' => 'Expenses', 'code_prefix' => '5', 'normal_balance' => 'debit', 'sort_order' => 50],
        ] as $class) {
            AccountClass::updateOrCreate(
                ['code_prefix' => $class['code_prefix']],
                $class + ['is_active' => true]
            );
        }
    }
}
