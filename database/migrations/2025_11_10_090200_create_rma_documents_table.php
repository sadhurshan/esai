<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rma_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rma_id')->constrained('rmas')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rma_id', 'document_id'], 'rma_documents_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rma_documents');
    }
};
