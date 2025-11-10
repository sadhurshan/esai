<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->cascadeOnDelete();
            $table->string('taxable_type');
            $table->unsignedBigInteger('taxable_id');
            $table->decimal('rate_percent', 6, 3);
            $table->bigInteger('amount_minor');
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->timestamps();

            $table->index(['taxable_type', 'taxable_id'], 'line_taxes_taxable_index');
            $table->index(['company_id', 'tax_code_id'], 'line_taxes_company_tax_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_taxes');
    }
};
