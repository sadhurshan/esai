<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_snapshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('forecast_snapshots', 'avg_daily_demand')) {
                $table->decimal('avg_daily_demand', 14, 3)->nullable()->after('demand_qty');
            }

            if (! Schema::hasColumn('forecast_snapshots', 'on_hand_qty')) {
                $table->decimal('on_hand_qty', 14, 3)->nullable()->after('avg_daily_demand');
            }

            if (! Schema::hasColumn('forecast_snapshots', 'on_order_qty')) {
                $table->decimal('on_order_qty', 14, 3)->nullable()->after('on_hand_qty');
            }

            if (! Schema::hasColumn('forecast_snapshots', 'safety_stock_qty')) {
                $table->decimal('safety_stock_qty', 14, 3)->nullable()->after('on_order_qty');
            }

            if (! Schema::hasColumn('forecast_snapshots', 'projected_runout_days')) {
                $table->decimal('projected_runout_days', 8, 2)->nullable()->after('safety_stock_qty');
            }

            if (! Schema::hasColumn('forecast_snapshots', 'horizon_days')) {
                $table->unsignedSmallInteger('horizon_days')->default(30)->after('projected_runout_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forecast_snapshots', function (Blueprint $table): void {
            foreach ([
                'avg_daily_demand',
                'on_hand_qty',
                'on_order_qty',
                'safety_stock_qty',
                'projected_runout_days',
                'horizon_days',
            ] as $column) {
                if (Schema::hasColumn('forecast_snapshots', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
