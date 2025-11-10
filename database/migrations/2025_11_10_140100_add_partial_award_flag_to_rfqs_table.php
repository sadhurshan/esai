<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'is_partially_awarded')) {
                $table->boolean('is_partially_awarded')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('rfqs', 'is_partially_awarded')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropColumn('is_partially_awarded');
            });
        }
    }
};
