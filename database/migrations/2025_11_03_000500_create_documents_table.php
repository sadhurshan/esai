<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('documentable_type', 160);
            $table->unsignedBigInteger('documentable_id');
            $table->enum('kind', ['rfq', 'quote', 'po', 'invoice', 'grn', 'ncr', 'supplier', 'template', 'other']);
            $table->string('path', 255);
            $table->string('filename', 191);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('hash_sha256', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id'], 'documents_documentable_index');
            $table->index(['company_id', 'kind'], 'documents_company_kind_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
