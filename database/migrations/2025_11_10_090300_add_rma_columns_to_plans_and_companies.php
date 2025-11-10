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
            $table->boolean('rma_enabled')->default(false);
            $table->unsignedInteger('rma_monthly_limit')->default(0);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('rma_monthly_used')->default(0);
        });

        DB::table('plans')->where('code', 'starter')->update([
            'rma_enabled' => false,
            'rma_monthly_limit' => 0,
        ]);

        DB::table('plans')->whereIn('code', ['growth', 'enterprise'])->update([
            'rma_enabled' => true,
            'rma_monthly_limit' => 20,
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('rma_monthly_used');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['rma_enabled', 'rma_monthly_limit']);
        });
    }
};
