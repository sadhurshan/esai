<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('approval_rule_id')->constrained('approval_rules')->cascadeOnDelete();
            $table->enum('target_type', ['rfq', 'purchase_order', 'change_order', 'invoice', 'ncr']);
            $table->unsignedBigInteger('target_id');
            $table->unsignedTinyInteger('level_no');
            $table->enum('status', ['pending', 'approved', 'rejected', 'skipped'])->default('pending');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'target_type', 'target_id'], 'approvals_company_target_index');
            $table->index(['status', 'level_no'], 'approvals_status_level_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
