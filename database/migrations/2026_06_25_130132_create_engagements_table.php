<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('engagement_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('engagement_no')->unique();
            $table->string('title');
            $table->string('status')->default('pending');
            $table->date('engagement_letter_sent_on')->nullable();
            $table->date('fee_agreement_sent_on')->nullable();
            $table->boolean('retainer_required')->default(false);
            $table->decimal('retainer_amount', 18, 2)->nullable();
            $table->date('client_accepted_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagements');
    }
};
