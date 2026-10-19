<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zonal_office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('security_type')->default('land_title');
            $table->string('movement_direction')->default('incoming')->index();
            $table->string('borrower_name');
            $table->string('borrower_contact')->nullable();
            $table->string('title_number')->nullable()->index();
            $table->string('land_reference')->nullable();
            $table->string('tenure')->nullable();
            $table->string('district')->nullable();
            $table->string('facility_account_no')->nullable()->index();
            $table->string('instruction_type')->nullable();
            $table->text('instruction_details')->nullable();
            $table->date('instruction_date')->nullable();
            $table->string('received_from')->nullable();
            $table->string('dispatched_to')->nullable();
            $table->string('returned_to')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->string('dispatch_acknowledged_by')->nullable();
            $table->dateTime('dispatch_acknowledged_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('archived_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_titles');
    }
};
