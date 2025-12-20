<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->uuid('workflow_id')->unique();
            $table->string('workflow_type', 100);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'rejected', 'aborted'])->default('pending');
            $table->unsignedInteger('current_step')->nullable();
            $table->json('steps_json');
            $table->timestamp('last_event_time')->nullable();
            $table->string('last_event_type', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_workflows');
    }
};
