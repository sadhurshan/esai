<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('invoices_per_month')->default(0)->after('rfqs_per_month');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('invoices_monthly_used')->default(0)->after('rfqs_monthly_used');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('invoices_monthly_used');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('invoices_per_month');
        });
    }
};
