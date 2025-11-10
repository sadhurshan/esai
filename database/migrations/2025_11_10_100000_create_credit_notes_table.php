<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('credit_number', 60);
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255);
            $table->enum('status', ['draft', 'issued', 'approved', 'rejected', 'applied'])->default('draft');
            $table->text('review_comment')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'credit_number'], 'credit_notes_company_number_unique');
            $table->index(['company_id', 'status'], 'credit_notes_company_status_index');
        });

        Schema::create('credit_note_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['credit_note_id', 'document_id'], 'credit_note_documents_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_documents');
        Schema::dropIfExists('credit_notes');
    }
};
