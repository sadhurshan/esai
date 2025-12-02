<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $roles = "('owner','buyer_admin','buyer_member','buyer_requester','supplier_admin','supplier_estimator','finance','platform_super','platform_support')";

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM{$roles} NOT NULL DEFAULT 'owner'");
        }

        if (Schema::hasTable('company_user') && Schema::hasColumn('company_user', 'role')) {
            DB::statement("ALTER TABLE company_user MODIFY COLUMN role ENUM{$roles} NOT NULL DEFAULT 'buyer_admin'");
        }

        if (Schema::hasTable('company_invitations') && Schema::hasColumn('company_invitations', 'role')) {
            DB::statement("ALTER TABLE company_invitations MODIFY COLUMN role ENUM{$roles} NOT NULL DEFAULT 'buyer_admin'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $roles = "('owner','buyer_admin','buyer_requester','supplier_admin','supplier_estimator','finance','platform_super','platform_support')";

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM{$roles} NOT NULL DEFAULT 'owner'");
        }

        if (Schema::hasTable('company_user') && Schema::hasColumn('company_user', 'role')) {
            DB::statement("ALTER TABLE company_user MODIFY COLUMN role ENUM{$roles} NOT NULL DEFAULT 'buyer_admin'");
        }

        if (Schema::hasTable('company_invitations') && Schema::hasColumn('company_invitations', 'role')) {
            DB::statement("ALTER TABLE company_invitations MODIFY COLUMN role ENUM{$roles} NOT NULL DEFAULT 'buyer_admin'");
        }
    }
};
