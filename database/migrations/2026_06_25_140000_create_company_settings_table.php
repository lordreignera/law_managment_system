<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('short_name', 40);
            $table->string('initials', 8)->default('K');
            $table->string('logo_path')->nullable();
            $table->string('tagline')->nullable();
            $table->string('login_heading')->nullable();
            $table->text('login_subheading')->nullable();
            $table->string('primary_color', 20)->default('#050505');
            $table->string('secondary_color', 20)->default('#ffffff');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->unique();
            $table->string('tax_type', 40)->default('vat');
            $table->decimal('rate', 8, 4)->default(0);
            $table->boolean('is_inclusive')->default(false);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 80)->unique();
            $table->string('channel', 30)->default('database');
            $table->string('event_key', 120)->unique();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('tax_settings');
        Schema::dropIfExists('company_settings');
    }
};
