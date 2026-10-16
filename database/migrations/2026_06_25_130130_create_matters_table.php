<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('opposing_counsels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('firm_name', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'firm_name']);
        });

        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ultimate_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('practice_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instruction_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opposing_counsel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matter_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained('shelves')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('title');
            $table->date('opened_on')->nullable();
            $table->date('instruction_date')->nullable();
            $table->date('closed_on')->nullable();
            $table->string('privacy_status')->default('public');
            $table->string('opposite_counsel')->nullable();
            $table->string('status')->default('inquiry');
            $table->string('priority')->default('normal');
            $table->text('description')->nullable();
            $table->text('client_summary')->nullable();
            $table->string('next_action')->nullable();
            $table->date('next_action_due')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('matter_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_role')->default('associate');
            $table->date('assigned_on')->nullable();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_assignments');
        Schema::dropIfExists('matters');
        Schema::dropIfExists('opposing_counsels');
        Schema::dropIfExists('instruction_types');
    }
};
