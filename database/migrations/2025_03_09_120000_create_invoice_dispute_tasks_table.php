<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_dispute_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->string('resolution_type', 80);
            $table->string('status', 40)->default('open');
            $table->string('owner_role', 80)->nullable();
            $table->boolean('requires_hold')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->text('summary');
            $table->json('actions')->nullable();
            $table->json('impacted_lines')->nullable();
            $table->json('next_steps')->nullable();
            $table->json('notes')->nullable();
            $table->json('reason_codes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'invoice_dispute_tasks_company_status');
            $table->index(['invoice_id', 'status'], 'invoice_dispute_tasks_invoice_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_dispute_tasks');
    }
};
