<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rfq_invitations')) {
            return;
        }

        Schema::table('rfq_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfq_invitations', 'status_new')) {
                $table->string('status_new', 20)->default('pending')->after('status');
            }
        });

        DB::table('rfq_invitations')->update([
            'status_new' => DB::raw("CASE status WHEN 'invited' THEN 'pending' ELSE status END"),
        ]);

        Schema::table('rfq_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('rfq_invitations', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('rfq_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('rfq_invitations', 'status_new')) {
                $table->renameColumn('status_new', 'status');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(<<<SQL
                ALTER TABLE `rfq_invitations`
                MODIFY COLUMN `status` ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending'
            SQL);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rfq_invitations')) {
            return;
        }

        Schema::table('rfq_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfq_invitations', 'status_old')) {
                $table->string('status_old', 20)->default('invited')->after('status');
            }
        });

        DB::table('rfq_invitations')->update([
            'status_old' => DB::raw("CASE status WHEN 'pending' THEN 'invited' ELSE status END"),
        ]);

        Schema::table('rfq_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('rfq_invitations', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('rfq_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('rfq_invitations', 'status_old')) {
                $table->renameColumn('status_old', 'status');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(<<<SQL
                ALTER TABLE `rfq_invitations`
                MODIFY COLUMN `status` ENUM('invited','accepted','declined') NOT NULL DEFAULT 'invited'
            SQL);
        }
    }
};
