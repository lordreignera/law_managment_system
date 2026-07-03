<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adr_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_conflict_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adr_no')->unique();
            $table->string('title');
            $table->string('conflict_party_name');
            $table->string('conflict_party_contact')->nullable();
            $table->string('method')->nullable();
            $table->date('resolved_on')->nullable();
            $table->string('response')->default('pending');
            $table->string('status')->default('open');
            $table->text('response_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adr_resolutions');
    }
};
