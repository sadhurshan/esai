<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->decimal('on_hand', 14, 3)->default(0);
            $table->decimal('allocated', 14, 3)->default(0);
            $table->decimal('on_order', 14, 3)->default(0);
            $table->string('uom', 16);
            $table->timestamps();

            $table->unique(['company_id', 'part_id', 'warehouse_id', 'bin_id']);
            $table->index(['part_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
