<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('risk_scores_enabled')->default(false)->after('analytics_history_months');
            $table->unsignedSmallInteger('risk_history_months')->default(12)->after('risk_scores_enabled');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('risk_scores_monthly_used')->default(0)->after('analytics_last_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('risk_scores_monthly_used');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['risk_scores_enabled', 'risk_history_months']);
        });
    }
};
