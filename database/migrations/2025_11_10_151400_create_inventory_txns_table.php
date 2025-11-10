<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_txns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->enum('type', [
                'receive',
                'issue',
                'adjust_in',
                'adjust_out',
                'transfer_out',
                'transfer_in',
                'return_in',
                'return_out',
            ]);
            $table->decimal('qty', 14, 3);
            $table->string('uom', 16);
            $table->string('ref_type', 64)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'part_id']);
            $table->index(['warehouse_id', 'bin_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_txns');
    }
};
