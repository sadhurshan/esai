<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'cycle_time',
                'otif',
                'response_rate',
                'spend',
                'forecast_accuracy',
            ]);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('value', 12, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'type', 'period_start', 'period_end'], 'analytics_snapshots_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
