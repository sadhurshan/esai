<?php

use App\Enums\RmaStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rmas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('po_lines')->nullOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 255);
            $table->text('description')->nullable();
            $table->enum('resolution_requested', ['repair', 'replacement', 'credit', 'refund', 'other'])->default('repair');
            $table->enum('status', RmaStatus::values())->default(RmaStatus::Raised->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('review_outcome', ['approved', 'rejected'])->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'rmas_company_status_index');
            $table->index(['purchase_order_id', 'purchase_order_line_id'], 'rmas_po_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rmas');
    }
};
