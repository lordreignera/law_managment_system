<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_payments')) {
            if (! Schema::hasColumn('invoice_payments', 'chart_account_id')) {
                Schema::table('invoice_payments', function (Blueprint $table) {
                    $table->foreignId('chart_account_id')->nullable()->after('invoice_id')->constrained('chart_accounts')->nullOnDelete();
                });
            }

            return;
        }

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('paid_on');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
