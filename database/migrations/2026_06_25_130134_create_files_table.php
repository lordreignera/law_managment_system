<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adr_resolution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_number')->unique();
            $table->string('file_name');
            $table->decimal('agreed_fee_amount', 18, 2)->nullable();
            $table->date('engagement_letter_sent_on')->nullable();
            $table->date('fee_agreement_sent_on')->nullable();
            $table->boolean('retainer_required')->default(false);
            $table->decimal('retainer_amount', 18, 2)->nullable();
            $table->string('retainer_payment_source')->nullable();
            $table->date('client_accepted_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
