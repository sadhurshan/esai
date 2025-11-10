<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'inventory_enabled')) {
                $table->boolean('inventory_enabled')->default(false)->after('maintenance_enabled');
            }

            if (! Schema::hasColumn('plans', 'inventory_history_months')) {
                $table->unsignedInteger('inventory_history_months')->default(12)->after('inventory_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'inventory_history_months')) {
                $table->dropColumn('inventory_history_months');
            }

            if (Schema::hasColumn('plans', 'inventory_enabled')) {
                $table->dropColumn('inventory_enabled');
            }
        });
    }
};
