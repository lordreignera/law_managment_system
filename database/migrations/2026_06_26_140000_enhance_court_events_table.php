<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_events', function (Blueprint $table) {
            $table->foreignId('court_id')->nullable()->after('matter_id')->constrained()->nullOnDelete();
            $table->text('outcome')->nullable()->after('notes');
            $table->string('next_step')->nullable()->after('outcome');
            $table->date('next_step_due')->nullable()->after('next_step');
            $table->softDeletes();
            $table->index(['status', 'starts_at']);
            $table->index('next_step_due');
        });
    }

    public function down(): void
    {
        Schema::table('court_events', function (Blueprint $table) {
            $table->dropForeign(['court_id']);
            $table->dropColumn(['court_id', 'outcome', 'next_step', 'next_step_due', 'deleted_at']);
            $table->dropIndex(['status', 'starts_at']);
            $table->dropIndex(['next_step_due']);
        });
    }
};
