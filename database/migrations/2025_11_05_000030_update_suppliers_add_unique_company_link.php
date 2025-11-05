<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce a single supplier profile per company while keeping legacy rows nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        if (Schema::hasColumn('suppliers', 'company_id') && ! $this->indexExists('suppliers', 'suppliers_company_id_unique')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unique(['company_id'], 'suppliers_company_id_unique');
            });
        }

        if (Schema::hasColumn('suppliers', 'status') && ! $this->indexExists('suppliers', 'suppliers_status_index')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->index('status', 'suppliers_status_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        if ($this->indexExists('suppliers', 'suppliers_status_index')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropIndex('suppliers_status_index');
            });
        }

        if ($this->indexExists('suppliers', 'suppliers_company_id_unique')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropUnique('suppliers_company_id_unique');
            });
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
};
