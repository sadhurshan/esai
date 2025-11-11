<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'exports_enabled')) {
                $table->boolean('exports_enabled')->default(false)->after('tax_engine_enabled');
            }

            if (! Schema::hasColumn('plans', 'export_row_limit')) {
                $table->unsignedInteger('export_row_limit')->default(50000)->after('exports_enabled');
            }
        });

        if (
            Schema::hasColumn('plans', 'data_export_enabled')
            && Schema::hasColumn('plans', 'exports_enabled')
        ) {
            DB::statement('UPDATE plans SET exports_enabled = data_export_enabled');
        }

        if (Schema::hasColumn('plans', 'export_row_limit') && Schema::hasColumn('plans', 'export_history_days')) {
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('UPDATE plans SET export_row_limit = GREATEST(1000, COALESCE(export_history_days, 0) * 1000)');
            } else {
                DB::table('plans')->select('id', 'export_history_days')->chunkById(100, function ($plans): void {
                    foreach ($plans as $plan) {
                        $historyDays = (int) ($plan->export_history_days ?? 0);
                        $limit = max(1000, $historyDays * 1000);

                        DB::table('plans')->where('id', $plan->id)->update(['export_row_limit' => $limit]);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'export_row_limit')) {
                $table->dropColumn('export_row_limit');
            }

            if (Schema::hasColumn('plans', 'exports_enabled')) {
                $table->dropColumn('exports_enabled');
            }
        });
    }
};