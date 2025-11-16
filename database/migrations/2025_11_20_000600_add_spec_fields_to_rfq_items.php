<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfq_items', 'method')) {
                $table->string('method', 160)->nullable()->after('spec');
            }

            if (! Schema::hasColumn('rfq_items', 'material')) {
                $table->string('material', 160)->nullable()->after('method');
            }

            if (! Schema::hasColumn('rfq_items', 'tolerance')) {
                $table->string('tolerance', 120)->nullable()->after('material');
            }

            if (! Schema::hasColumn('rfq_items', 'finish')) {
                $table->string('finish', 160)->nullable()->after('tolerance');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $columns = ['method', 'material', 'tolerance', 'finish'];

        $updates = [];

        foreach ($columns as $column) {
            if (Schema::hasColumn('rfqs', $column) && Schema::hasColumn('rfq_items', $column)) {
                $updates["items.$column"] = DB::raw("COALESCE(items.$column, rfqs.$column)");
            }
        }

        if ($updates !== []) {
            DB::table('rfq_items as items')
                ->join('rfqs', 'items.rfq_id', '=', 'rfqs.id')
                ->update($updates);
        }
    }

    public function down(): void
    {
        Schema::table('rfq_items', function (Blueprint $table): void {
            foreach (['method', 'material', 'tolerance', 'finish'] as $column) {
                if (Schema::hasColumn('rfq_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
