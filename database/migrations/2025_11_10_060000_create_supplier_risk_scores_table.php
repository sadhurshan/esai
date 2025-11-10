<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_risk_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('supplier_id')->constrained()->cascadeOnUpdate();
            $table->decimal('on_time_delivery_rate', 5, 2)->nullable();
            $table->decimal('defect_rate', 5, 2)->nullable();
            $table->decimal('price_volatility', 6, 4)->nullable();
            $table->decimal('lead_time_volatility', 6, 4)->nullable();
            $table->decimal('responsiveness_rate', 5, 2)->nullable();
            $table->decimal('overall_score', 6, 4)->nullable();
            $table->enum('risk_grade', ['low', 'medium', 'high'])->nullable();
            $table->json('badges_json')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'supplier_id']);
            $table->index('risk_grade');
            $table->index(['company_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_risk_scores');
    }
};
