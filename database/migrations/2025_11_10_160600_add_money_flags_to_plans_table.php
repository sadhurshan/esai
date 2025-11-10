<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'multi_currency_enabled')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->boolean('multi_currency_enabled')->default(false)->after('pr_enabled');
                $table->boolean('tax_engine_enabled')->default(false)->after('multi_currency_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'tax_engine_enabled')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn(['multi_currency_enabled', 'tax_engine_enabled']);
            });
        }
    }
};
