<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('rfqs', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('rfqs', 'title')) {
                $table->string('title', 200)->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('rfqs', 'tolerance_finish')) {
                $table->string('tolerance_finish', 120)->nullable()->after('method');
            }

            if (! Schema::hasColumn('rfqs', 'incoterm')) {
                $table->string('incoterm', 8)->nullable()->after('tolerance_finish');
            }

            if (! Schema::hasColumn('rfqs', 'currency')) {
                $table->char('currency', 3)->default('USD')->after('incoterm');
            }

            if (! Schema::hasColumn('rfqs', 'open_bidding')) {
                $table->boolean('open_bidding')->default(false)->after('currency');
            }

            if (! Schema::hasColumn('rfqs', 'publish_at')) {
                $table->dateTime('publish_at')->nullable()->after('open_bidding');
            }

            if (! Schema::hasColumn('rfqs', 'due_at')) {
                $table->dateTime('due_at')->nullable()->after('publish_at');
            }

            if (! Schema::hasColumn('rfqs', 'close_at')) {
                $table->dateTime('close_at')->nullable()->after('due_at');
            }

            if (! Schema::hasColumn('rfqs', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('status');
            }

            if (! Schema::hasColumn('rfqs', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! $this->indexExists('rfqs', 'rfqs_company_status_due_idx')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->index(['company_id', 'status', 'due_at'], 'rfqs_company_status_due_idx');
            });
        }

        if (
            in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && ! $this->indexExists('rfqs', 'rfqs_title_fulltext')
        ) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->fullText(['title'], 'rfqs_title_fulltext');
            });
        }

        Schema::create('rfq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('part_name', 160);
            $table->text('spec')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('uom', 16)->default('pcs');
            $table->decimal('target_price', 12, 2)->nullable();

            $table->unique(['rfq_id', 'line_no']);
            $table->index('rfq_id');
        });

        Schema::create('rfq_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['invited', 'accepted', 'declined'])->default('invited');
            $table->timestamps();

            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('rfq_clarifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('kind', ['question', 'answer', 'amendment']);
            $table->text('message');
            $table->unsignedBigInteger('attachment_id')->nullable();
            $table->unsignedInteger('rfq_version')->default(1);
            $table->timestamps();

            $table->index(['rfq_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_clarifications');
        Schema::dropIfExists('rfq_invitations');
        Schema::dropIfExists('rfq_items');

        $companyForeign = $this->getForeignKeyName('rfqs', 'company_id');
        $creatorForeign = $this->getForeignKeyName('rfqs', 'created_by');

        if ($companyForeign !== null) {
            Schema::table('rfqs', function (Blueprint $table) use ($companyForeign): void {
                $table->dropForeign($companyForeign);
            });
        }

        if ($creatorForeign !== null) {
            Schema::table('rfqs', function (Blueprint $table) use ($creatorForeign): void {
                $table->dropForeign($creatorForeign);
            });
        }

        if (
            in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && $this->indexExists('rfqs', 'rfqs_title_fulltext')
        ) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropFullText('rfqs_title_fulltext');
            });
        }

        if ($this->indexExists('rfqs', 'rfqs_company_status_due_idx')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropIndex('rfqs_company_status_due_idx');
            });
        }

        Schema::table('rfqs', function (Blueprint $table): void {
            if (Schema::hasColumn('rfqs', 'company_id')) {
                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('rfqs', 'created_by')) {
                $table->dropColumn('created_by');
            }

            foreach ([
                'title',
                'tolerance_finish',
                'incoterm',
                'currency',
                'open_bidding',
                'publish_at',
                'due_at',
                'close_at',
                'version',
            ] as $column) {
                if (Schema::hasColumn('rfqs', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('rfqs', 'deleted_at')) {
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
