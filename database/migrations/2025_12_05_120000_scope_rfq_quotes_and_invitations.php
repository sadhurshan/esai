<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rfq_quotes')) {
            Schema::table('rfq_quotes', function (Blueprint $table): void {
                if (! Schema::hasColumn('rfq_quotes', 'company_id')) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('companies')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('rfq_quotes', 'deleted_at')) {
                    $table->softDeletes();
                }

                if (! $this->indexExists('rfq_quotes', 'rfq_quotes_company_rfq_idx')) {
                    $table->index(['company_id', 'rfq_id'], 'rfq_quotes_company_rfq_idx');
                }
            });

            $this->backfillCompanyIds('rfq_quotes');
        }

        if (Schema::hasTable('rfq_invitations')) {
            $hasRfqForeign = $this->foreignKeyExists('rfq_invitations', 'rfq_invitations_rfq_id_foreign');
            $hasSupplierForeign = $this->foreignKeyExists('rfq_invitations', 'rfq_invitations_supplier_id_foreign');

            Schema::table('rfq_invitations', function (Blueprint $table) use ($hasRfqForeign, $hasSupplierForeign): void {
                if ($hasRfqForeign) {
                    $table->dropForeign('rfq_invitations_rfq_id_foreign');
                }

                if ($hasSupplierForeign) {
                    $table->dropForeign('rfq_invitations_supplier_id_foreign');
                }

                if ($this->indexExists('rfq_invitations', 'rfq_invitations_rfq_id_supplier_id_unique')) {
                    $table->dropUnique('rfq_invitations_rfq_id_supplier_id_unique');
                }

                if (! Schema::hasColumn('rfq_invitations', 'company_id')) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('companies')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('rfq_invitations', 'deleted_at')) {
                    $table->softDeletes();
                }

                if (! $this->indexExists('rfq_invitations', 'rfq_invitations_company_idx')) {
                    $table->index('company_id', 'rfq_invitations_company_idx');
                }

                if (! $this->indexExists('rfq_invitations', 'rfq_invitations_rfq_supplier_deleted_unique')) {
                    $table->unique(['rfq_id', 'supplier_id', 'deleted_at'], 'rfq_invitations_rfq_supplier_deleted_unique');
                }

                if ($hasRfqForeign) {
                    $table->foreign('rfq_id', 'rfq_invitations_rfq_id_foreign')
                        ->references('id')
                        ->on('rfqs')
                        ->cascadeOnDelete();
                }

                if ($hasSupplierForeign) {
                    $table->foreign('supplier_id', 'rfq_invitations_supplier_id_foreign')
                        ->references('id')
                        ->on('suppliers')
                        ->cascadeOnDelete();
                }
            });

            $this->backfillCompanyIds('rfq_invitations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rfq_quotes')) {
            Schema::table('rfq_quotes', function (Blueprint $table): void {
                if ($this->indexExists('rfq_quotes', 'rfq_quotes_company_rfq_idx')) {
                    $table->dropIndex('rfq_quotes_company_rfq_idx');
                }

                if (Schema::hasColumn('rfq_quotes', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }

                if (Schema::hasColumn('rfq_quotes', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('rfq_invitations')) {
            Schema::table('rfq_invitations', function (Blueprint $table): void {
                if ($this->indexExists('rfq_invitations', 'rfq_invitations_rfq_supplier_deleted_unique')) {
                    $table->dropUnique('rfq_invitations_rfq_supplier_deleted_unique');
                }

                if ($this->indexExists('rfq_invitations', 'rfq_invitations_company_idx')) {
                    $table->dropIndex('rfq_invitations_company_idx');
                }

                if (! $this->indexExists('rfq_invitations', 'rfq_invitations_rfq_id_supplier_id_unique')) {
                    $table->unique(['rfq_id', 'supplier_id'], 'rfq_invitations_rfq_id_supplier_id_unique');
                }

                if (Schema::hasColumn('rfq_invitations', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }

                if (Schema::hasColumn('rfq_invitations', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
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

    private function foreignKeyExists(string $table, string $foreign): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => false,
            'mysql', 'mariadb' => DB::table('information_schema.table_constraints')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('constraint_name', $foreign)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists(),
            'pgsql' => DB::table('information_schema.table_constraints')
                ->where('table_catalog', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('constraint_name', $foreign)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists(),
            default => false,
        };
    }

    private function backfillCompanyIds(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable('rfqs') || ! Schema::hasColumn('rfqs', 'company_id')) {
            return;
        }

        DB::table($table)
            ->select($table.'.id', 'rfqs.company_id')
            ->join('rfqs', 'rfqs.id', '=', $table.'.rfq_id')
            ->whereNull($table.'.company_id')
            ->orderBy($table.'.id')
            ->chunk(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    if ($row->company_id === null) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['company_id' => $row->company_id]);
                }
            });
    }
};
