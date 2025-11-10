<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('target_type', ['rfq', 'purchase_order', 'change_order', 'invoice', 'ncr']);
            $table->decimal('threshold_min', 12, 2)->default(0);
            $table->decimal('threshold_max', 12, 2)->nullable();
            $table->json('levels_json');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'target_type', 'active'], 'approval_rules_company_target_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
