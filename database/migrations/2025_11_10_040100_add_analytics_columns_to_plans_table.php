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
            $table->boolean('analytics_enabled')->default(false)->after('erp_integrations_max');
            $table->unsignedInteger('analytics_history_months')->default(12)->after('analytics_enabled');
        });

        DB::table('plans')->whereIn('code', ['growth', 'enterprise'])
            ->update([
                'analytics_enabled' => true,
                'analytics_history_months' => 24,
            ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['analytics_enabled', 'analytics_history_months']);
        });
    }
};
