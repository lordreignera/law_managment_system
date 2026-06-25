<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billable_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->decimal('hourly_rate', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_rates');
    }
};
