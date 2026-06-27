<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recovery_accounts', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
        });

        Schema::table('recovery_activities', function (Blueprint $table) {
            $table->decimal('amount_paid', 18, 2)->nullable()->after('promised_on');
        });
    }

    public function down(): void
    {
        Schema::table('recovery_activities', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });

        Schema::table('recovery_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
        });
    }
};
