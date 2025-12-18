<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $hasAssetLinks = Schema::hasTable('asset_procedure_links');
        $hasProcedureSteps = Schema::hasTable('procedure_steps');

        if ($hasAssetLinks) {
            if (! Schema::hasColumn('asset_procedure_links', 'company_id')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->unsignedBigInteger('company_id')->nullable()->after('id');
                });
            }

            if (! Schema::hasColumn('asset_procedure_links', 'deleted_at')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }

            if ($this->indexExists('asset_procedure_links', 'asset_procedure_links_asset_id_maintenance_procedure_id_unique')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropUnique('asset_procedure_links_asset_id_maintenance_procedure_id_unique');
                });
            }

            if (! $this->indexExists('asset_procedure_links', 'asset_procedure_links_asset_proc_deleted_unique')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->unique(
                        ['asset_id', 'maintenance_procedure_id', 'deleted_at'],
                        'asset_procedure_links_asset_proc_deleted_unique'
                    );
                });
            }

            if (! $this->indexExists('asset_procedure_links', 'asset_proc_links_company_asset_index')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->index(['company_id', 'asset_id'], 'asset_proc_links_company_asset_index');
                });
            }

            if (! $this->indexExists('asset_procedure_links', 'asset_proc_links_company_proc_index')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->index(['company_id', 'maintenance_procedure_id'], 'asset_proc_links_company_proc_index');
                });
            }

            if (! $this->foreignKeyExists('asset_procedure_links', 'asset_procedure_links_company_id_foreign')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }
        }

        if ($hasProcedureSteps) {
            if (! Schema::hasColumn('procedure_steps', 'company_id')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->unsignedBigInteger('company_id')->nullable()->after('id');
                });
            }

            if (! Schema::hasColumn('procedure_steps', 'deleted_at')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }

            if ($this->indexExists('procedure_steps', 'procedure_steps_maintenance_procedure_id_step_no_unique')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->dropUnique('procedure_steps_maintenance_procedure_id_step_no_unique');
                });
            }

            if (! $this->indexExists('procedure_steps', 'procedure_steps_proc_step_deleted_unique')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->unique(
                        ['maintenance_procedure_id', 'step_no', 'deleted_at'],
                        'procedure_steps_proc_step_deleted_unique'
                    );
                });
            }

            if (! $this->indexExists('procedure_steps', 'procedure_steps_company_proc_index')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->index(['company_id', 'maintenance_procedure_id'], 'procedure_steps_company_proc_index');
                });
            }

            if (! $this->foreignKeyExists('procedure_steps', 'procedure_steps_company_id_foreign')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }
        }

        if ($hasAssetLinks) {
            $this->backfillAssetProcedureLinkCompanies();

            Schema::table('asset_procedure_links', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }

        if ($hasProcedureSteps) {
            $this->backfillProcedureStepCompanies();

            Schema::table('procedure_steps', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('asset_procedure_links')) {
            if ($this->foreignKeyExists('asset_procedure_links', 'asset_procedure_links_company_id_foreign')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropForeign(['company_id']);
                });
            }

            if ($this->indexExists('asset_procedure_links', 'asset_proc_links_company_asset_index')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropIndex('asset_proc_links_company_asset_index');
                });
            }

            if ($this->indexExists('asset_procedure_links', 'asset_proc_links_company_proc_index')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropIndex('asset_proc_links_company_proc_index');
                });
            }

            if ($this->indexExists('asset_procedure_links', 'asset_procedure_links_asset_proc_deleted_unique')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropUnique('asset_procedure_links_asset_proc_deleted_unique');
                });
            }

            if (! $this->indexExists('asset_procedure_links', 'asset_procedure_links_asset_id_maintenance_procedure_id_unique')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->unique(['asset_id', 'maintenance_procedure_id']);
                });
            }

            if (Schema::hasColumn('asset_procedure_links', 'company_id')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropColumn('company_id');
                });
            }

            if (Schema::hasColumn('asset_procedure_links', 'deleted_at')) {
                Schema::table('asset_procedure_links', function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }

        if (Schema::hasTable('procedure_steps')) {
            if ($this->foreignKeyExists('procedure_steps', 'procedure_steps_company_id_foreign')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->dropForeign(['company_id']);
                });
            }

            if ($this->indexExists('procedure_steps', 'procedure_steps_company_proc_index')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->dropIndex('procedure_steps_company_proc_index');
                });
            }

            if ($this->indexExists('procedure_steps', 'procedure_steps_proc_step_deleted_unique')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->dropUnique('procedure_steps_proc_step_deleted_unique');
                });
            }

            if (! $this->indexExists('procedure_steps', 'procedure_steps_maintenance_procedure_id_step_no_unique')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->unique(['maintenance_procedure_id', 'step_no']);
                });
            }

            if (Schema::hasColumn('procedure_steps', 'company_id')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->dropColumn('company_id');
                });
            }

            if (Schema::hasColumn('procedure_steps', 'deleted_at')) {
                Schema::table('procedure_steps', function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    private function backfillAssetProcedureLinkCompanies(): void
    {
        DB::table('asset_procedure_links as apl')
            ->select([
                'apl.id',
                'assets.company_id as asset_company_id',
                'procedures.company_id as procedure_company_id',
            ])
            ->leftJoin('assets', 'assets.id', '=', 'apl.asset_id')
            ->leftJoin('maintenance_procedures as procedures', 'procedures.id', '=', 'apl.maintenance_procedure_id')
            ->orderBy('apl.id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $companyId = $row->asset_company_id ?? $row->procedure_company_id;

                    if ($companyId === null) {
                        continue;
                    }

                    DB::table('asset_procedure_links')
                        ->where('id', $row->id)
                        ->update([
                            'company_id' => $companyId,
                            'updated_at' => now(),
                        ]);
                }
            }, 'apl.id');
    }

    private function backfillProcedureStepCompanies(): void
    {
        DB::table('procedure_steps as steps')
            ->select([
                'steps.id',
                'procedures.company_id as procedure_company_id',
            ])
            ->leftJoin('maintenance_procedures as procedures', 'procedures.id', '=', 'steps.maintenance_procedure_id')
            ->orderBy('steps.id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    if ($row->procedure_company_id === null) {
                        continue;
                    }

                    DB::table('procedure_steps')
                        ->where('id', $row->id)
                        ->update([
                            'company_id' => $row->procedure_company_id,
                            'updated_at' => now(),
                        ]);
                }
            }, 'steps.id');
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

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->count() > 0;
    }

    private function postgresIndexExists(string $table, string $index): bool
    {
        return DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->count() > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlForeignKeyExists($table, $constraint),
            'pgsql' => $this->postgresForeignKeyExists($table, $constraint),
            default => false,
        };
    }

    private function mysqlForeignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.table_constraints')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->count() > 0;
    }

    private function postgresForeignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->count() > 0;
    }
};
