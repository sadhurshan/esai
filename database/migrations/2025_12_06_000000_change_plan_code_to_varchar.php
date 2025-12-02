<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'plan_code')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE companies MODIFY COLUMN plan_code VARCHAR(64) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('companies', 'plan_code')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE companies MODIFY COLUMN plan_code ENUM('community','starter','growth','enterprise') NULL");
    }
};
