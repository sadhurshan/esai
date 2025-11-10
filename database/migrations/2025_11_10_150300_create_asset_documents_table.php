<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');
    }
};
