<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add supplier approval flags and verification metadata to companies.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'supplier_status')) {
                $table->enum('supplier_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('status');
            }

            if (! Schema::hasColumn('companies', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('supplier_status');
            }

            if (! Schema::hasColumn('companies', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_verified');
            }

            if (! Schema::hasColumn('companies', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! $this->indexExists('companies', 'companies_supplier_status_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->index('supplier_status', 'companies_supplier_status_index');
            });
        }

        if (! $this->indexExists('companies', 'companies_is_verified_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->index('is_verified', 'companies_is_verified_index');
            });
        }

        if (! $this->indexExists('companies', 'companies_verified_by_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->index('verified_by', 'companies_verified_by_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $verifiedByForeignKey = $this->getForeignKeyName('companies', 'verified_by');

        if ($verifiedByForeignKey !== null) {
            Schema::table('companies', function (Blueprint $table) use ($verifiedByForeignKey): void {
                $table->dropForeign($verifiedByForeignKey);
            });
        }

        if ($this->indexExists('companies', 'companies_verified_by_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropIndex('companies_verified_by_index');
            });
        }

        if ($this->indexExists('companies', 'companies_is_verified_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropIndex('companies_is_verified_index');
            });
        }

        if ($this->indexExists('companies', 'companies_supplier_status_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropIndex('companies_supplier_status_index');
            });
        }

        if (Schema::hasColumn('companies', 'verified_by')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropColumn('verified_by');
            });
        }

        foreach (['verified_at', 'is_verified', 'supplier_status'] as $column) {
            if (Schema::hasColumn('companies', $column)) {
                Schema::table('companies', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
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
            if ((string) ($row->name ?? '') === $index) {
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
};
