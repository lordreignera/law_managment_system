<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->foreignId('requisition_category_id')->nullable()->after('matter_id')->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->softDeletes();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropForeign(['requisition_category_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['requisition_category_id', 'reviewed_by', 'reviewed_at', 'review_notes', 'deleted_at']);
            $table->dropIndex(['status']);
        });
    }
};
