<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('office_location')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bank_id', 'name']);
        });

        Schema::create('centenary_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->string('office_location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('centenary_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centenary_region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('office_location')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['centenary_region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centenary_branches');
        Schema::dropIfExists('centenary_regions');
        Schema::dropIfExists('bank_branches');
    }
};
