<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('component_type', 40)->default('earning');
            $table->string('calculation_method', 40)->default('fixed');
            $table->string('calculation_basis', 40)->default('basic_salary');
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('rate', 10, 4)->nullable();
            $table->text('formula')->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_nssf_liable')->default(false);
            $table->boolean('is_employer_cost')->default(false);
            $table->boolean('is_recurring')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['component_type', 'is_active']);
        });

        Schema::create('payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('rule_type', 40)->default('custom');
            $table->string('calculation_method', 40)->default('percentage');
            $table->decimal('rate', 10, 4)->nullable();
            $table->text('formula')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_employer_cost')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['rule_type', 'effective_from', 'is_active']);
        });

        Schema::create('payroll_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('tin', 80)->nullable();
            $table->string('nssf_number', 80)->nullable();
            $table->string('tax_residency', 30)->default('resident');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->nullOnDelete();
            $table->string('payment_method', 30)->default('bank');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'tax_residency']);
        });

        Schema::create('payroll_salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_employee_profile_id')->constrained('payroll_employee_profiles')->cascadeOnDelete();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->foreignId('currency_type_id')->nullable()->constrained('currency_types')->nullOnDelete();
            $table->string('pay_frequency', 30)->default('monthly');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payroll_employee_profile_id', 'effective_from'], 'payroll_salary_profile_effective_idx');
        });

        Schema::create('payroll_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_salary_record_id')->constrained('payroll_salary_records')->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained('payroll_components')->restrictOnDelete();
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('rate', 10, 4)->nullable();
            $table->text('formula')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payroll_salary_record_id', 'payroll_component_id'], 'payroll_salary_record_component_unique');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('payment_on')->nullable();
            $table->string('status', 40)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['starts_on', 'ends_on']);
            $table->index('status');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->unique()->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('status', 40)->default('draft');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('totals')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payroll_employee_profile_id')->nullable()->constrained('payroll_employee_profiles')->nullOnDelete();
            $table->string('employee_name');
            $table->string('staff_no', 80)->nullable();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('gross_pay', 18, 2)->default(0);
            $table->decimal('taxable_pay', 18, 2)->default(0);
            $table->decimal('employee_deductions', 18, 2)->default(0);
            $table->decimal('employer_contributions', 18, 2)->default(0);
            $table->decimal('net_pay', 18, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id']);
        });

        Schema::create('payroll_line_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->nullable()->constrained('payroll_components')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('component_type', 40);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('rate', 10, 4)->nullable();
            $table->string('calculation_method', 40)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_employer_cost')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_line_id')->unique()->constrained('payroll_run_lines')->cascadeOnDelete();
            $table->string('payslip_number', 100)->unique();
            $table->string('status', 30)->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('payroll_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->unique()->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('reference', 80)->unique();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_statutory_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('submission_type', 30);
            $table->string('status', 30)->default('draft');
            $table->date('due_on')->nullable();
            $table->date('filed_on')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('receipt_number', 120)->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'submission_type'], 'payroll_run_submission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_statutory_submissions');
        Schema::dropIfExists('payroll_payment_batches');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_line_components');
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_salary_components');
        Schema::dropIfExists('payroll_salary_records');
        Schema::dropIfExists('payroll_employee_profiles');
        Schema::dropIfExists('payroll_rules');
        Schema::dropIfExists('payroll_components');
    }
};
