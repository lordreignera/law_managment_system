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
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zonal_office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('borrower_name');
            $table->string('instruction_type')->nullable();
            $table->date('instruction_date')->nullable();
            $table->string('received_from')->nullable();
            $table->string('returned_to')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_titles');
    }
};
