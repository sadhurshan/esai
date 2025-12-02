<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_application_id', 'supplier_document_id'], 'supplier_application_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_application_documents');
    }
};
