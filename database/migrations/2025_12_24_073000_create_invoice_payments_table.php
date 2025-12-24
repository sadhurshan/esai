<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->decimal('amount', 15, 4)->default(0);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 191);
            $table->string('payment_method', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'paid_at'], 'invoice_payments_company_paid_at_index');
            $table->index(['invoice_id', 'paid_at'], 'invoice_payments_invoice_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
