<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('pr_number', 40);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'converted', 'cancelled'])->default('draft');
            $table->char('currency', 3);
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'pr_number'], 'purchase_requisitions_company_number_unique');
            $table->index(['company_id', 'status'], 'purchase_requisitions_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};
