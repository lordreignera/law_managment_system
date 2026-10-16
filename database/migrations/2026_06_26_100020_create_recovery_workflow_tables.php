<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_promise_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_activity_id')->constrained('recovery_activities')->cascadeOnDelete();
            $table->foreignId('recovery_account_id')->constrained('recovery_accounts')->cascadeOnDelete();
            $table->string('recipient_phone', 50);
            $table->date('scheduled_for');
            $table->string('status')->default('pending')->index();
            $table->dateTime('sent_at')->nullable();
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
            $table->unique(['recovery_activity_id', 'scheduled_for'], 'recovery_promise_reminder_unique');
        });

        Schema::create('recovery_skip_trace_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_account_id')->constrained('recovery_accounts')->cascadeOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->index();
            $table->string('status')->default('pending')->index();
            $table->string('nin')->nullable();
            $table->string('nssf_number')->nullable();
            $table->string('crb_reference')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->json('result')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_skip_trace_checks');
        Schema::dropIfExists('recovery_promise_reminder_logs');
    }
};
