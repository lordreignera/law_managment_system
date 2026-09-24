<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->unsignedSmallInteger('round')->default(1)->after('approver_id');
            $table->index(['approvable_type', 'approvable_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropIndex(['approvable_type', 'approvable_id', 'round']);
            $table->dropColumn('round');
        });
    }
};
