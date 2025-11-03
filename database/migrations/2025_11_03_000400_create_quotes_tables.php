<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency', 3)->default('USD');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('min_order_qty')->nullable();
            $table->unsignedInteger('lead_time_days');
            $table->text('note')->nullable();
            $table->enum('status', ['draft', 'submitted', 'withdrawn', 'awarded', 'lost'])->default('submitted');
            $table->unsignedInteger('revision_no')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rfq_id', 'supplier_id', 'revision_no'], 'quotes_rfq_supplier_revision_unique');
            $table->index(['rfq_id', 'supplier_id', 'status'], 'quotes_rfq_supplier_status_index');
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->cascadeOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('lead_time_days');
            $table->string('note', 255)->nullable();

            $table->unique(['quote_id', 'rfq_item_id'], 'quote_items_quote_rfq_item_unique');
            $table->index('quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
