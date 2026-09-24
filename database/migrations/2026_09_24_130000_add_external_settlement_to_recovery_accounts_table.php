<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recovery_accounts', function (Blueprint $table) {
            $table->timestamp('settled_externally_at')->nullable()->after('amount_recovered');
        });
    }

    public function down(): void
    {
        Schema::table('recovery_accounts', function (Blueprint $table) {
            $table->dropColumn('settled_externally_at');
        });
    }
};
