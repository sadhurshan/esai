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
                    'owner',
                    'buyer_admin',
                    'buyer_requester',
                    'supplier_admin',
                    'supplier_estimator',
                    'finance',
                    'platform_super',
                    'platform_support',
                ])->default('owner')->after('password');
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
        $companyForeign = $this->getForeignKeyName('users', 'company_id');

        if ($companyForeign !== null) {
            Schema::table('users', function (Blueprint $table) use ($companyForeign): void {
                $table->dropForeign($companyForeign);
            });
        }

        if ($this->indexExists('users', 'users_company_id_role_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_company_id_role_index');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'company_id')) {
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

    private function getForeignKeyName(string $table, string $column): ?string
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlForeignKeyName($table, $column),
            'pgsql' => $this->postgresForeignKeyName($table, $column),
            'sqlite' => $this->sqliteForeignKeyName($table, $column),
            default => null,
        };
    }

    private function mysqlForeignKeyName(string $table, string $column): ?string
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->whereNotNull('referenced_table_name')
            ->value('constraint_name');
    }

    private function postgresForeignKeyName(string $table, string $column): ?string
    {
        $sql = <<<'SQL'
            SELECT
                tc.constraint_name
            FROM
                information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                    AND tc.table_schema = kcu.table_schema
            WHERE
                tc.constraint_type = 'FOREIGN KEY'
                AND tc.table_name = ?
                AND kcu.column_name = ?
        SQL;

        $result = DB::select($sql, [$table, $column]);

        return $result[0]->constraint_name ?? null;
    }

    private function sqliteForeignKeyName(string $table, string $column): ?string
    {
        $rows = DB::select("PRAGMA foreign_key_list('".$table."')");

        foreach ($rows as $row) {
            if ((string) ($row->from ?? '') === $column) {
                return $row->id !== null ? 'fk_'.$table.'_'.$row->id : null;
            }
        }

        return null;
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
