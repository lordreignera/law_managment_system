<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->string('court_level')->nullable();
            $table->string('station')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('judicial_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('title')->nullable();
            $table->string('chambers')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['court_id', 'name']);
        });

        Schema::create('court_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('judicial_officer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('court_name')->nullable();
            $table->string('case_number')->nullable();
            $table->string('judicial_officer')->nullable();
            $table->string('event_type')->default('mention');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->text('outcome')->nullable();
            $table->string('next_step')->nullable();
            $table->date('next_step_due')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'starts_at']);
            $table->index('next_step_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_events');
        Schema::dropIfExists('judicial_officers');
        Schema::dropIfExists('courts');
    }
};
