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
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
