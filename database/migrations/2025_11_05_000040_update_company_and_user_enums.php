<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand company and user enums for supplier onboarding lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'status')) {
                DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('pending','pending_verification','active','suspended','rejected') NOT NULL DEFAULT 'pending_verification'");
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner','buyer_admin','buyer_requester','supplier_admin','supplier_estimator','finance','platform_super','platform_support') NOT NULL DEFAULT 'owner'");
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'status')) {
                DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending'");
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer_admin','buyer_requester','supplier_admin','supplier_estimator','finance','platform_super','platform_support') NOT NULL DEFAULT 'buyer_admin'");
            }
        }
    }
};
