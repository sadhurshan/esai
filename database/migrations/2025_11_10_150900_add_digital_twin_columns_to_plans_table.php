<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'digital_twin_enabled')) {
                $table->boolean('digital_twin_enabled')->default(false)->after('quote_revisions_enabled');
            }

            if (! Schema::hasColumn('plans', 'maintenance_enabled')) {
                $table->boolean('maintenance_enabled')->default(false)->after('digital_twin_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'maintenance_enabled')) {
                $table->dropColumn('maintenance_enabled');
            }

            if (Schema::hasColumn('plans', 'digital_twin_enabled')) {
                $table->dropColumn('digital_twin_enabled');
            }
        });
    }
};
