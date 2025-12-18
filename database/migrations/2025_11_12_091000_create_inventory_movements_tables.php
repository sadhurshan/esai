<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('movement_number', 64);
            $table->enum('type', ['receipt', 'issue', 'transfer', 'adjust']);
            $table->enum('status', ['draft', 'posted', 'voided'])->default('posted');
            $table->timestamp('moved_at');
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'movement_number']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('inventory_movement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movement_id')->constrained('inventory_movements')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->string('uom', 16);
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('from_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('reason', 191)->nullable();
            $table->decimal('resulting_on_hand', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['movement_id', 'line_number']);
            $table->index(['company_id', 'part_id']);
        });

        Schema::table('inventory_txns', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_txns', 'movement_id')) {
                $table->foreignId('movement_id')->nullable()->after('company_id')->constrained('inventory_movements')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_txns', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_txns', 'movement_id')) {
                $table->dropForeign(['movement_id']);
                $table->dropColumn('movement_id');
            }
        });

        Schema::dropIfExists('inventory_movement_lines');
        Schema::dropIfExists('inventory_movements');
    }
};
