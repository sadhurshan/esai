<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->string('po_number', 40)->unique();
            $table->char('currency', 3)->default('USD');
            $table->string('incoterm', 8)->nullable();
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->enum('status', ['draft', 'sent', 'acknowledged', 'confirmed', 'cancelled'])->default('draft');
            $table->unsignedInteger('revision_no')->default(0);
            $table->foreignId('pdf_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'purchase_orders_company_status_index');
            $table->index(['rfq_id', 'quote_id'], 'purchase_orders_rfq_quote_index');
        });

        Schema::create('po_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->nullable()->constrained('rfq_items')->nullOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('description', 200);
            $table->unsignedInteger('quantity');
            $table->string('uom', 16);
            $table->decimal('unit_price', 12, 2);
            $table->date('delivery_date')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no'], 'po_lines_purchase_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
