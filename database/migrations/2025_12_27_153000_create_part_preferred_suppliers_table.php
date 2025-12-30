<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_preferred_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 191)->nullable();
            $table->unsignedTinyInteger('priority');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'part_id', 'priority', 'deleted_at'], 'part_pref_suppliers_priority_unique');
            $table->index(['company_id', 'part_id', 'supplier_id'], 'part_pref_suppliers_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_preferred_suppliers');
    }
};
