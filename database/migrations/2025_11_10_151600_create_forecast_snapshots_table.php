<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forecast_snapshots')) {
            return;
        }

        Schema::create('forecast_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('demand_qty', 14, 3);
            $table->enum('method', ['actual', 'sma', 'ema']);
            $table->decimal('alpha', 4, 3)->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'part_id', 'period_start', 'period_end', 'method'],
                'forecast_snapshots_company_part_period_method_unique'
            );
            $table->index(['part_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_snapshots');
    }
};
