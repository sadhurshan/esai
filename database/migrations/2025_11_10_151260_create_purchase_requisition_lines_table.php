<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('description', 200);
            $table->string('uom', 16);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->foreignId('suggestion_id')->nullable()->constrained('reorder_suggestions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_requisition_id', 'purchase_requisition_lines_requisition_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_lines');
    }
};
