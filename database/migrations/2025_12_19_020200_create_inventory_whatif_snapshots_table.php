<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_whatif_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('scenario_name');
            $table->string('part_identifier')->nullable();
            $table->json('input_snapshot');
            $table->json('result_snapshot');
            $table->decimal('projected_stockout_risk', 5, 4);
            $table->decimal('expected_stockout_days', 10, 2);
            $table->decimal('expected_holding_cost_change', 14, 2);
            $table->text('recommendation');
            $table->json('assumptions_json')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('needs_human_review')->default(false);
            $table->json('warnings_json')->nullable();
            $table->json('citations_json')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_whatif_snapshots');
    }
};
