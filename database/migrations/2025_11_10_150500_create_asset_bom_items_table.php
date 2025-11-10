<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_bom_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('uom', 16);
            $table->enum('criticality', ['low', 'medium', 'high'])->default('medium');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_bom_items');
    }
};
