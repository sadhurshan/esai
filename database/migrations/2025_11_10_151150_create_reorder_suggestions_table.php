<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reorder_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('suggested_qty', 14, 3);
            $table->string('reason', 255);
            $table->date('horizon_start');
            $table->date('horizon_end');
            $table->enum('method', ['minmax', 'sma', 'ema']);
            $table->dateTime('generated_at');
            $table->dateTime('accepted_at')->nullable();
            $table->enum('status', ['open', 'accepted', 'dismissed', 'converted'])->default('open');
            $table->foreignId('pr_id')->nullable()->constrained('purchase_requisitions')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['part_id', 'warehouse_id']);
            $table->index('pr_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorder_suggestions');
    }
};
