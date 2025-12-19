<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_document_indexes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('doc_id', 191);
            $table->string('doc_version', 100);
            $table->string('source_type', 120);
            $table->string('title', 255);
            $table->string('mime_type', 120);
            $table->timestamp('indexed_at')->nullable();
            $table->unsignedInteger('indexed_chunks')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'doc_id', 'doc_version'], 'ai_doc_indexes_doc_unique');
            $table->index(['company_id', 'source_type'], 'ai_doc_indexes_company_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_indexes');
    }
};
