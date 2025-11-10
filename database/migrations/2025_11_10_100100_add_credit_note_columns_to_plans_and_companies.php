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
            $table->boolean('credit_notes_enabled')->default(false);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('credit_notes_monthly_used')->default(0);
        });

        DB::table('plans')->where('code', 'starter')->update([
            'credit_notes_enabled' => false,
        ]);

        DB::table('plans')->whereIn('code', ['growth', 'enterprise'])->update([
            'credit_notes_enabled' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('credit_notes_monthly_used');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('credit_notes_enabled');
        });
    }
};
