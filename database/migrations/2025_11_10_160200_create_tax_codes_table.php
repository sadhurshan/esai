<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->enum('type', ['vat', 'gst', 'sales', 'withholding', 'custom']);
            $table->decimal('rate_percent', 6, 3)->nullable();
            $table->boolean('is_compound')->default(false);
            $table->boolean('active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code'], 'tax_codes_company_code_unique');
            $table->index(['company_id', 'active'], 'tax_codes_company_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
