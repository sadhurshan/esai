<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_document_numberings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('prefix', 12)->default('');
            $table->unsignedTinyInteger('seq_len')->default(4);
            $table->unsignedBigInteger('next')->default(1);
            $table->enum('reset', ['never', 'yearly'])->default('never');
            $table->unsignedSmallInteger('last_reset_year')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document_type']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_numberings');
    }
};
