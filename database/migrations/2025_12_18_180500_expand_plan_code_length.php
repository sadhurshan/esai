<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'code')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE plans MODIFY code VARCHAR(64) NOT NULL');

            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->string('code', 64)->unique()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'code')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE plans MODIFY code VARCHAR(32) NOT NULL');

            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->string('code', 32)->unique()->change();
        });
    }
};
