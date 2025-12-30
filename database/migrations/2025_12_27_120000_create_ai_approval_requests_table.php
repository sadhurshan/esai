<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->uuid('workflow_id');
            $table->foreignId('workflow_step_id')->nullable()->constrained('ai_workflow_steps')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('step_index')->nullable();
            $table->string('entity_type', 100);
            $table->string('entity_id', 64)->nullable();
            $table->string('step_type', 100)->nullable();
            $table->string('approver_role', 100)->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workflow_id')
                ->references('workflow_id')
                ->on('ai_workflows')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['company_id', 'status']);
            $table->index(['workflow_id', 'step_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_approval_requests');
    }
};
