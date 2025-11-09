<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('inspected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->enum('status', ['pending', 'complete', 'ncr_raised'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number'], 'goods_receipt_notes_company_number_unique');
            $table->index('company_id', 'goods_receipt_notes_company_id_index');
            $table->index('status', 'goods_receipt_notes_status_index');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('po_lines')->cascadeOnDelete();
            $table->unsignedInteger('received_qty');
            $table->unsignedInteger('accepted_qty');
            $table->unsignedInteger('rejected_qty')->default(0);
            $table->text('defect_notes')->nullable();
            $table->json('attachment_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['goods_receipt_note_id', 'purchase_order_line_id'],
                'goods_receipt_lines_note_line_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipt_notes');
    }
};
