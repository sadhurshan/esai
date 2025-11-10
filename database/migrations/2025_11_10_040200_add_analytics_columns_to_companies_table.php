<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('analytics_usage_months')->default(0)->after('invoices_monthly_used');
            $table->timestamp('analytics_last_generated_at')->nullable()->after('analytics_usage_months');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['analytics_usage_months', 'analytics_last_generated_at']);
        });
    }
};
