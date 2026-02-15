<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_document_extractions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('document_version');
            $table->string('source_type', 120)->nullable();
            $table->string('filename', 191)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->enum('status', ['pending', 'completed', 'error'])->default('pending');
            $table->json('extracted_json')->nullable();
            $table->json('gdt_flags_json')->nullable();
            $table->json('similar_parts_json')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document_id', 'document_version'], 'ai_doc_extract_unique');
            $table->index(['company_id', 'status'], 'ai_doc_extract_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_extractions');
    }
};
