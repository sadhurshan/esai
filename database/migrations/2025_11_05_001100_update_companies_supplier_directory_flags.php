<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'directory_visibility')) {
                $table->enum('directory_visibility', ['private', 'public'])->default('private')->after('supplier_status');
            }

            if (! Schema::hasColumn('companies', 'supplier_profile_completed_at')) {
                $table->dateTime('supplier_profile_completed_at')->nullable()->after('directory_visibility');
            }
        });

        if (! $this->indexExists('companies', 'companies_directory_visibility_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->index('directory_visibility', 'companies_directory_visibility_index');
            });
        }

        if (Schema::hasColumn('companies', 'supplier_status')) {
            $this->updateSupplierStatusEnum(['none', 'pending', 'approved', 'rejected', 'suspended']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        if ($this->indexExists('companies', 'companies_directory_visibility_index')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropIndex('companies_directory_visibility_index');
            });
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'supplier_profile_completed_at')) {
                $table->dropColumn('supplier_profile_completed_at');
            }

            if (Schema::hasColumn('companies', 'directory_visibility')) {
                $table->dropColumn('directory_visibility');
            }
        });

        if (Schema::hasColumn('companies', 'supplier_status')) {
            $this->updateSupplierStatusEnum(['none', 'pending', 'approved', 'rejected']);
        }
    }

    private function updateSupplierStatusEnum(array $values): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $allowed = implode("','", $values);

        DB::statement(sprintf(
            "ALTER TABLE companies MODIFY COLUMN supplier_status ENUM('%s') NOT NULL DEFAULT '%s'",
            $allowed,
            $values[0]
        ));
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
