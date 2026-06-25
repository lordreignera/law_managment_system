<?php

namespace Database\Seeders;

use App\Models\InstructionType;
use Illuminate\Database\Seeder;

class InstructionTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['New Matter', 'Legal Opinion', 'Court Filing', 'Title Perfection', 'Recovery Instruction', 'Advisory Assignment'] as $index => $name) {
            InstructionType::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }
    }
}
