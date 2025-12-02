<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncrs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('po_lines')->cascadeOnDelete();
            $table->foreignId('raised_by_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->enum('disposition', ['rework', 'return', 'accept_as_is'])->nullable();
            $table->string('reason', 500);
            $table->json('documents_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'ncrs_company_status_index');
            $table->index('goods_receipt_note_id', 'ncrs_grn_index');
            $table->index('purchase_order_line_id', 'ncrs_po_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncrs');
    }
};
