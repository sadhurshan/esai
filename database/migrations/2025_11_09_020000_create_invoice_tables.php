<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('invoice_number', 60);
            $table->char('currency', 3);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'overdue', 'disputed'])->default('pending');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'invoice_number'], 'invoices_company_number_unique');
            $table->index(['company_id', 'status'], 'invoices_company_status_index');
            $table->index('supplier_id', 'invoices_supplier_id_index');
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('po_line_id')->nullable()->constrained('po_lines')->nullOnDelete();
            $table->string('description', 200);
            $table->unsignedInteger('quantity');
            $table->string('uom', 16)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['invoice_id', 'po_line_id'], 'invoice_lines_invoice_po_unique');
        });

        Schema::create('invoice_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->enum('result', ['matched', 'qty_mismatch', 'price_mismatch', 'unmatched']);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('invoice_id', 'invoice_matches_invoice_id_index');
            $table->index('result', 'invoice_matches_result_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_matches');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
