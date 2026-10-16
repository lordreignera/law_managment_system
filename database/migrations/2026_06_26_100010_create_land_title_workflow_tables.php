<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_title_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('land_title_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type')->index();
            $table->string('from_party')->nullable();
            $table->string('to_party')->nullable();
            $table->dateTime('moved_at');
            $table->string('acknowledged_by')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('land_title_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('land_title_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('pending')->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evidence_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('land_title_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('land_title_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('pending')->index();
            $table->dateTime('due_at')->nullable()->index();
            $table->unsignedInteger('reminder_days_before')->default(1);
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('land_title_milestone_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('land_title_milestone_id')->constrained('land_title_milestones', 'id', 'lt_milestone_reminder_fk')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reminder_type')->index();
            $table->date('target_date');
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['land_title_milestone_id', 'recipient_id', 'reminder_type', 'target_date'], 'land_title_milestone_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_title_milestone_reminder_logs');
        Schema::dropIfExists('land_title_milestones');
        Schema::dropIfExists('land_title_checklist_items');
        Schema::dropIfExists('land_title_movements');
    }
};
