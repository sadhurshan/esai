<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('min_qty', 14, 3)->nullable();
            $table->decimal('max_qty', 14, 3)->nullable();
            $table->decimal('safety_stock', 14, 3)->nullable();
            $table->decimal('reorder_qty', 14, 3)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('lot_size', 14, 3)->nullable();
            $table->enum('policy', ['minmax', 'fixed', 'forecast_driven'])->default('minmax');
            $table->timestamps();

            $table->unique(['company_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_settings');
    }
};
