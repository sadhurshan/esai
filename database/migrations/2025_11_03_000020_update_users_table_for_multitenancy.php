<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', [
                    'buyer_admin',
                    'buyer_requester',
                    'supplier_admin',
                    'supplier_estimator',
                    'finance',
                    'platform_super',
                    'platform_support',
                ])->default('buyer_admin')->after('password');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! $this->indexExists('users', 'users_company_id_role_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(['company_id', 'role'], 'users_company_id_role_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('users', 'users_company_id_role_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_company_id_role_index');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }

            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
    
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection()->getDriverName();

        return match ($connection) {
            'sqlite' => $this->sqliteIndexExists($table, $index),
            'mysql', 'mariadb' => $this->mysqlIndexExists($table, $index),
            'pgsql' => $this->postgresIndexExists($table, $index),
            default => false,
        };
    }

    private function sqliteIndexExists(string $table, string $index): bool
    {
        $rows = DB::select("PRAGMA index_list('".$table."')");

        foreach ($rows as $row) {
            if ((string) $row->name === $index) {
                return true;
            }
        }

        return false;
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $count = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->count();

        return $count > 0;
    }

    private function postgresIndexExists(string $table, string $index): bool
    {
        $count = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->count();

        return $count > 0;
    }
};
