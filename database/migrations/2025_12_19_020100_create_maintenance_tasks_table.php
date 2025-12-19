<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('maintenance_procedure_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('summary')->nullable();
            $table->string('urgency')->nullable();
            $table->string('environment')->nullable();
            $table->string('asset_reference')->nullable();
            $table->json('safety_notes_json');
            $table->json('diagnostic_steps_json');
            $table->json('likely_causes_json');
            $table->json('recommended_actions_json');
            $table->json('escalation_rules_json');
            $table->json('citations_json')->nullable();
            $table->json('meta')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('needs_human_review')->default(false);
            $table->json('warnings_json')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};
