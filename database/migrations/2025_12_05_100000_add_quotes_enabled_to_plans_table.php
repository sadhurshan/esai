<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'quotes_enabled')) {
                $table->boolean('quotes_enabled')->default(false)->after('global_search_enabled');
            }
        });

        DB::table('plans')
            ->whereIn('code', ['growth', 'enterprise'])
            ->update(['quotes_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'quotes_enabled')) {
                $table->dropColumn('quotes_enabled');
            }
        });
    }
};
