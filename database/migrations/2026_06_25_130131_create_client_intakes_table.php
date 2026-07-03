<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('practice_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('preferred_lawyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('intake_no')->unique();
            $table->string('client_type')->default('individual');
            $table->string('client_name');
            $table->string('organization_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('legal_issue');
            $table->string('urgency')->default('normal');
            $table->string('referral_source')->nullable();
            $table->string('referral_name')->nullable();
            $table->string('referral_contact')->nullable();
            $table->text('summary')->nullable();
            $table->string('status')->default('pending_review');
            $table->string('review_decision')->default('pending');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->date('consultation_on')->nullable();
            $table->time('consultation_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('intake_conflict_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_intake_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_conflict_parties');
        Schema::dropIfExists('client_intakes');
    }
};
