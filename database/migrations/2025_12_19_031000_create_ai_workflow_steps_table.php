<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->uuid('workflow_id');
            $table->unsignedInteger('step_index');
            $table->string('action_type', 100);
            $table->json('input_json');
            $table->json('draft_json')->nullable();
            $table->json('output_json')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workflow_id')
                ->references('workflow_id')
                ->on('ai_workflows')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['workflow_id', 'step_index']);
            $table->index(['company_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_workflow_steps');
    }
};
