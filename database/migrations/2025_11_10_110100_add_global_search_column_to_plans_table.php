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
            if (! Schema::hasColumn('plans', 'global_search_enabled')) {
                $table->boolean('global_search_enabled')->default(false)->after('credit_notes_enabled');
            }
        });

        DB::table('plans')->update(['global_search_enabled' => false]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'global_search_enabled')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('global_search_enabled');
            });
        }
    }
};
