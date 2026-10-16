<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('title')->nullable();
            $table->string('category')->nullable();
            $table->string('document_key')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->boolean('is_current')->default(true);
            $table->boolean('is_client_visible')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('bundle_type')->default('pleadings');
            $table->string('status')->default('draft');
            $table->text('description')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_bundle_items');
        Schema::dropIfExists('document_bundles');
        Schema::dropIfExists('attachments');
    }
};
