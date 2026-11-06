<?php

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Branch::query()->each(fn (Branch $branch) => Department::seedStandardForBranch($branch));
    }

    public function down(): void
    {
        // These are baseline records and may already have staff assigned.
        // They should not be removed during a rollback.
    }
};
