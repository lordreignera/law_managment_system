<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankBranch;
use Illuminate\Database\Seeder;

class BankBranchSeeder extends Seeder
{
    public function run(): void
    {
        Bank::orderBy('name')->get()->each(function (Bank $bank, int $index) {
            BankBranch::firstOrCreate(
                ['bank_id' => $bank->id, 'name' => 'Head Office'],
                [
                    'office_location' => 'Head office',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        });
    }
}
