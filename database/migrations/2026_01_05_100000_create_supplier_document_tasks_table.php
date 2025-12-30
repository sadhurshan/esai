<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_document_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_document_id')->nullable()->constrained('supplier_documents')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 120);
            $table->string('status', 32)->default('pending');
            $table->boolean('is_required')->default(true);
            $table->unsignedTinyInteger('priority')->default(3);
            $table->timestamp('due_at')->nullable();
            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['due_at']);
            $table->unique(['supplier_id', 'document_type', 'deleted_at'], 'supplier_document_tasks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_document_tasks');
    }
};
