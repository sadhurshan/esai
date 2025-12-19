<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->enum('feature', ['forecast', 'supplier_risk']);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('metric_name', 100);
            $table->decimal('metric_value', 14, 6);
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['company_id', 'feature', 'metric_name', 'window_end'], 'ai_model_metrics_company_feature_metric_window_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_metrics');
    }
};
