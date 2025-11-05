<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create supplier applications to manage buyer-initiated approvals.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_applications')) {
            return;
        }

        Schema::create('supplier_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('form_json')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'supplier_applications_company_id_status_index');
            $table->index('submitted_by', 'supplier_applications_submitted_by_index');
            $table->index('reviewed_by', 'supplier_applications_reviewed_by_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_applications')) {
            return;
        }

        Schema::drop('supplier_applications');
    }
};
