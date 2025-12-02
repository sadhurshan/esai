<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'type')) {
                $table->enum('type', ['ready_made', 'manufacture'])
                    ->default('manufacture')
                    ->after('title');
            }

            if (! Schema::hasColumn('rfqs', 'tolerance_finish')) {
                $table->string('tolerance_finish', 120)
                    ->nullable()
                    ->after('method');
            }

            if (! Schema::hasColumn('rfqs', 'version')) {
                $table->unsignedInteger('version')
                    ->default(1)
                    ->after('rfq_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (Schema::hasColumn('rfqs', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('rfqs', 'tolerance_finish')) {
                $table->dropColumn('tolerance_finish');
            }

            if (Schema::hasColumn('rfqs', 'version')) {
                $table->dropColumn('version');
            }
        });
    }
};
