<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('portfolio_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('recovery_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->string('portfolio_type')->nullable();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->decimal('total_principal', 18, 2)->default(0);
            $table->decimal('total_outstanding', 18, 2)->default(0);
            $table->unsignedInteger('assigned_count')->default(0);
            $table->string('status')->default('imported');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('recovery_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recovery_import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('portfolio_type')->nullable()->index();
            $table->unsignedInteger('import_row_number')->nullable();
            $table->string('account_number')->nullable()->index();
            $table->string('customer_number')->nullable()->index();
            $table->string('debtor_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('employer')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('region')->nullable();
            $table->string('collector_name')->nullable();
            $table->string('operative_account')->nullable();
            $table->unsignedInteger('days_past_due')->nullable();
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('arrears_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->decimal('amount_recovered', 18, 2)->default(0);
            $table->string('currency', 10)->default('UGX');
            $table->string('bucket')->nullable();
            $table->text('collateral_held')->nullable();
            $table->text('cause_of_default')->nullable();
            $table->string('status')->default('active');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recovery_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type')->default('call');
            $table->dateTime('activity_at');
            $table->decimal('promised_amount', 18, 2)->nullable();
            $table->date('promised_on')->nullable();
            $table->decimal('amount_paid', 18, 2)->nullable();
            $table->text('notes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_activities');
        Schema::dropIfExists('recovery_accounts');
        Schema::dropIfExists('recovery_import_batches');
        Schema::dropIfExists('recovery_clients');
    }
};
