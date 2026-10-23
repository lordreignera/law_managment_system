<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recovery_client_id')->constrained()->cascadeOnDelete();
            $table->string('portfolio_type', 100);
            $table->string('sheet_name')->nullable();
            $table->json('column_map');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['recovery_client_id', 'portfolio_type'], 'recovery_template_client_portfolio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_import_templates');
    }
};
