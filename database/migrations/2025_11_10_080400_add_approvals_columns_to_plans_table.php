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
            $table->boolean('approvals_enabled')->default(false)->after('risk_history_months');
            $table->unsignedTinyInteger('approval_levels_limit')->default(0)->after('approvals_enabled');
        });

        DB::table('plans')->where('code', 'growth')->update([
            'approvals_enabled' => true,
            'approval_levels_limit' => 3,
        ]);

        DB::table('plans')->where('code', 'enterprise')->update([
            'approvals_enabled' => true,
            'approval_levels_limit' => 5,
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['approvals_enabled', 'approval_levels_limit']);
        });
    }
};
