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
            $table->boolean('quote_revisions_enabled')->default(false);
        });

        DB::table('plans')->whereIn('code', ['growth', 'enterprise'])->update([
            'quote_revisions_enabled' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('quote_revisions_enabled');
        });
    }
};
