<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('suppliers', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('suppliers', 'country')) {
                $table->string('country', 2)->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('suppliers', 'city')) {
                $table->string('city', 120)->nullable()->after('country');
            }

            if (! Schema::hasColumn('suppliers', 'email')) {
                $table->string('email', 191)->nullable()->after('city');
            }

            if (! Schema::hasColumn('suppliers', 'phone')) {
                $table->string('phone', 60)->nullable()->after('email');
            }

            if (! Schema::hasColumn('suppliers', 'website')) {
                $table->string('website', 191)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('suppliers', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->after('website');
            }

            if (! Schema::hasColumn('suppliers', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->default(0)->after('capabilities');
            }

            if (! Schema::hasColumn('suppliers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! $this->indexExists('suppliers', 'suppliers_company_id_status_index')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->index(['company_id', 'status'], 'suppliers_company_id_status_index');
            });
        }

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // TODO: clarify with spec - MySQL cannot build FULLTEXT indexes on JSON columns, so introduce a generated TEXT column.
            if (! Schema::hasColumn('suppliers', 'capabilities_search')) {
                Schema::table('suppliers', function (Blueprint $table): void {
                    $table->text('capabilities_search')->storedAs('JSON_UNQUOTE(`capabilities`)');
                });
            }

            if (! $this->indexExists('suppliers', 'suppliers_name_city_capabilities_fulltext')) {
                Schema::table('suppliers', function (Blueprint $table): void {
                    $table->fullText(['name', 'city', 'capabilities_search'], 'suppliers_name_city_capabilities_fulltext');
                });
            }
        }

        Schema::create('supplier_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('type', ['iso', 'tax', 'insurance', 'registration', 'other']);
            $table->unsignedBigInteger('document_id');
            $table->date('expires_at')->nullable();
            $table->enum('status', ['valid', 'expiring', 'expired'])->default('valid');
            $table->timestamps();

            $table->unique(['supplier_id', 'document_id']);
            $table->index(['supplier_id', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');

        $companyForeign = $this->getForeignKeyName('suppliers', 'company_id');

        if ($companyForeign !== null) {
            Schema::table('suppliers', function (Blueprint $table) use ($companyForeign): void {
                $table->dropForeign($companyForeign);
            });
        }

        if ($this->indexExists('suppliers', 'suppliers_company_id_status_index')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropIndex('suppliers_company_id_status_index');
            });
        }

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            if ($this->indexExists('suppliers', 'suppliers_name_city_capabilities_fulltext')) {
                Schema::table('suppliers', function (Blueprint $table): void {
                    $table->dropFullText('suppliers_name_city_capabilities_fulltext');
                });
            }

            Schema::table('suppliers', function (Blueprint $table): void {
                if (Schema::hasColumn('suppliers', 'capabilities_search')) {
                    $table->dropColumn('capabilities_search');
                }
            });
        }

        Schema::table('suppliers', function (Blueprint $table): void {
            if (Schema::hasColumn('suppliers', 'company_id')) {
                $table->dropColumn('company_id');
            }

            foreach (['country', 'city', 'email', 'phone', 'website', 'status', 'rating_avg'] as $column) {
                if (Schema::hasColumn('suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('suppliers', 'deleted_at')) {
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
