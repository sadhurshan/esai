<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfp_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rfp_id')->constrained('rfps')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('submitted')->index();
            $table->decimal('price_total', 15, 2)->nullable();
            $table->unsignedBigInteger('price_total_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->text('approach_summary');
            $table->text('schedule_summary');
            $table->text('value_add_summary')->nullable();
            $table->unsignedInteger('attachments_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rfp_id', 'status']);
            $table->index(['company_id', 'rfp_id'], 'rfp_proposals_company_rfp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfp_proposals');
    }
};
