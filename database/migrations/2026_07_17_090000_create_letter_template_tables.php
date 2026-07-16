<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letterhead_id')->nullable()->constrained('letterheads')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('category', 40)->default('general')->index();
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('legal_letters', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('letter_template_id')->nullable()->constrained('letter_templates')->nullOnDelete();
            $table->foreignId('letterhead_id')->nullable()->constrained('letterheads')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained('matters')->nullOnDelete();
            $table->foreignId('recovery_account_id')->nullable()->constrained('recovery_accounts')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('letter_type', 40)->default('general')->index();
            $table->string('recipient_name');
            $table->string('recipient_contact')->nullable();
            $table->string('recipient_email')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->string('status', 40)->default('draft')->index();
            $table->date('letter_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('signature_mode', 40)->default('none');
            $table->string('signature_path', 2048)->nullable();
            $table->boolean('client_visible')->default(false)->index();
            $table->text('approval_notes')->nullable();
            $table->text('sent_notes')->nullable();
            $table->text('received_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('letter_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_letter_id')->constrained('legal_letters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_letter_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_shares');
        Schema::dropIfExists('legal_letters');
        Schema::dropIfExists('letter_templates');
    }
};
