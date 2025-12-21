<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_training_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->enum('feature', ['forecast', 'risk', 'rag', 'actions', 'workflows', 'chat']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->string('microservice_job_id')->nullable();
            $table->json('parameters_json')->nullable();
            $table->json('result_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'feature']);
            $table->index(['status', 'created_at']);
            $table->index('microservice_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_training_jobs');
    }
};
