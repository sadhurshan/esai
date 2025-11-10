<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->json('data_json');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['quote_id', 'revision_no'], 'quote_revisions_quote_revision_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_revisions');
    }
};
