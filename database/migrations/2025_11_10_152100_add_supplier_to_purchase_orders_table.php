<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('company_id')->constrained('suppliers')->nullOnDelete();
            }

            if (! $this->indexExists('purchase_orders', 'purchase_orders_company_supplier_index')) {
                $table->index(['company_id', 'supplier_id'], 'purchase_orders_company_supplier_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if ($this->indexExists('purchase_orders', 'purchase_orders_company_supplier_index')) {
                $table->dropIndex('purchase_orders_company_supplier_index');
            }

            if (Schema::hasColumn('purchase_orders', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection()->getDriverName();

        return match ($connection) {
            'mysql', 'mariadb' => $this->mysqlIndexExists($table, $index),
            'pgsql' => $this->postgresIndexExists($table, $index),
            'sqlite' => $this->sqliteIndexExists($table, $index),
            default => false,
        };
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return Schema::getConnection()->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function postgresIndexExists(string $table, string $index): bool
    {
        return Schema::getConnection()->table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->exists();
    }

    private function sqliteIndexExists(string $table, string $index): bool
    {
        $rows = Schema::getConnection()->select("PRAGMA index_list('".$table."')");

        foreach ($rows as $row) {
            if ((string) ($row->name ?? '') === $index) {
                return true;
            }
        }

        return false;
    }
};
