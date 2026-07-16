<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->string('mapping_key', 120);
            $table->foreignId('chart_account_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['module', 'mapping_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_account_mappings');
    }
};
