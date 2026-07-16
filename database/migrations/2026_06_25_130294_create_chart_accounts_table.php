<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->string('account_number', 40)->unique();
            $table->string('name');
            $table->string('account_type', 40);
            $table->string('normal_balance', 10)->default('debit');
            $table->unsignedTinyInteger('level')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_bank_account')->default(false);
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_client_funds_account')->default(false);
            $table->string('currency_code', 10)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_column', 5)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_class_id', 'parent_id']);
            $table->index(['account_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_accounts');
    }
};
