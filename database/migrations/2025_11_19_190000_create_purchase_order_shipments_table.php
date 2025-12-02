<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('shipment_number', 40);
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->string('carrier', 120);
            $table->string('tracking_number', 120);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'shipment_number'], 'po_shipments_company_number_unique');
            $table->index(['purchase_order_id', 'status'], 'po_shipments_order_status_index');
        });

        Schema::create('purchase_order_shipment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_shipment_id')->constrained('purchase_order_shipments')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('po_lines')->cascadeOnDelete();
            $table->decimal('qty_shipped', 12, 3);
            $table->timestamps();

            $table->index(['purchase_order_line_id'], 'po_shipment_lines_po_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_shipment_lines');
        Schema::dropIfExists('purchase_order_shipments');
    }
};
