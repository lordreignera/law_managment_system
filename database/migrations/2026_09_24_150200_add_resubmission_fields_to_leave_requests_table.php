<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('resubmission_count')->default(0)->after('status');
            $table->timestamp('resubmitted_at')->nullable()->after('resubmission_count');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['resubmission_count', 'resubmitted_at']);
        });
    }
};
