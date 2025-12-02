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
            if (! Schema::hasColumn('rfqs', 'title')) {
                $table->string('title', 200)->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('rfqs', 'delivery_location')) {
                $table->string('delivery_location', 255)->nullable()->after('material');
            }

            if (! Schema::hasColumn('rfqs', 'quantity_total')) {
                $table->unsignedInteger('quantity_total')->default(0)->after('delivery_location');
            }

            if (! Schema::hasColumn('rfqs', 'rfq_version')) {
                $table->unsignedInteger('rfq_version')->default(1)->after('close_at');
            }

            if (! Schema::hasColumn('rfqs', 'attachments_count')) {
                $table->unsignedInteger('attachments_count')->default(0)->after('rfq_version');
            }

            if (! Schema::hasColumn('rfqs', 'meta')) {
                $table->json('meta')->nullable()->after('attachments_count');
            }
        });

        DB::table('rfqs')->update([
            'title' => DB::raw('COALESCE(title, item_name, number)'),
        ]);

        DB::table('rfqs')->update([
            'quantity_total' => DB::raw('COALESCE(quantity_total, quantity, 0)'),
            'delivery_location' => DB::raw('COALESCE(delivery_location, client_company)'),
            'rfq_version' => DB::raw('COALESCE(rfq_version, version_no, version, 1)'),
            'attachments_count' => DB::raw('COALESCE(attachments_count, 0)'),
        ]);

        Schema::table('rfqs', function (Blueprint $table): void {
            $table->string('title', 200)->nullable(false)->change();
            $table->unsignedInteger('quantity_total')->default(0)->change();
            $table->unsignedInteger('rfq_version')->default(1)->change();
            $table->unsignedInteger('attachments_count')->default(0)->change();
        });

        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'status_new')) {
                $table->string('status_new', 20)->default('draft')->after('status');
            }
        });

        DB::table('rfqs')->update([
            'status_new' => DB::raw("CASE status WHEN 'awaiting' THEN 'draft' WHEN 'open' THEN 'open' WHEN 'closed' THEN 'closed' WHEN 'awarded' THEN 'awarded' ELSE 'cancelled' END"),
            'open_bidding' => DB::raw('COALESCE(open_bidding, is_open_bidding, false)'),
        ]);

        $temporaryCompanyIndex = 'rfqs_company_id_tmp_idx';

        if (
            $this->indexExists('rfqs', 'rfqs_company_status_due_idx')
            && ! $this->indexExists('rfqs', $temporaryCompanyIndex)
        ) {
            Schema::table('rfqs', function (Blueprint $table) use ($temporaryCompanyIndex): void {
                $table->index('company_id', $temporaryCompanyIndex);
            });
        }

        foreach (['rfqs_company_status_due_idx', 'rfqs_status_index'] as $statusIndex) {
            if ($this->indexExists('rfqs', $statusIndex)) {
                Schema::table('rfqs', function (Blueprint $table) use ($statusIndex): void {
                    $table->dropIndex($statusIndex);
                });
            }
        }

        Schema::table('rfqs', function (Blueprint $table): void {
            if (Schema::hasColumn('rfqs', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('rfqs', function (Blueprint $table): void {
            if (Schema::hasColumn('rfqs', 'status_new')) {
                $table->renameColumn('status_new', 'status');
            }
        });

        Schema::table('rfqs', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->change();
            $table->boolean('open_bidding')->default(false)->change();
        });

        foreach (['rfqs_type_index', 'rfqs_deadline_at_index', 'rfqs_sent_at_index'] as $legacyIndex) {
            if ($this->indexExists('rfqs', $legacyIndex)) {
                Schema::table('rfqs', function (Blueprint $table) use ($legacyIndex): void {
                    $table->dropIndex($legacyIndex);
                });
            }
        }

        Schema::table('rfqs', function (Blueprint $table): void {
            $columns = [
                'item_name',
                'type',
                'quantity',
                'client_company',
                'deadline_at',
                'sent_at',
                'is_open_bidding',
                'cad_path',
                'tolerance_finish',
                'version',
                'version_no',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('rfqs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (! $this->indexExists('rfqs', 'rfqs_company_status_due_idx')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->index(['company_id', 'status', 'due_at'], 'rfqs_company_status_due_idx');
            });
        }

        if ($this->indexExists('rfqs', $temporaryCompanyIndex)) {
            Schema::table('rfqs', function (Blueprint $table) use ($temporaryCompanyIndex): void {
                $table->dropIndex($temporaryCompanyIndex);
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
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'item_name')) {
                $table->string('item_name')->nullable()->after('number');
            }

            if (! Schema::hasColumn('rfqs', 'type')) {
                $table->enum('type', ['ready_made', 'manufacture'])->nullable()->after('item_name');
            }

            if (! Schema::hasColumn('rfqs', 'quantity')) {
                $table->unsignedInteger('quantity')->nullable()->after('type');
            }

            if (! Schema::hasColumn('rfqs', 'client_company')) {
                $table->string('client_company')->nullable()->after('finish');
            }

            if (! Schema::hasColumn('rfqs', 'deadline_at')) {
                $table->timestamp('deadline_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('rfqs', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('deadline_at');
            }

            if (! Schema::hasColumn('rfqs', 'is_open_bidding')) {
                $table->boolean('is_open_bidding')->default(false)->after('sent_at');
            }

            if (! Schema::hasColumn('rfqs', 'cad_path')) {
                $table->string('cad_path')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('rfqs', 'tolerance_finish')) {
                $table->string('tolerance_finish', 120)->nullable()->after('finish');
            }

            if (! Schema::hasColumn('rfqs', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('close_at');
            }

            if (! Schema::hasColumn('rfqs', 'version_no')) {
                $table->unsignedInteger('version_no')->default(1)->after('version');
            }
        });

        DB::table('rfqs')->update([
            'item_name' => DB::raw('COALESCE(item_name, title)'),
            'quantity' => DB::raw('COALESCE(quantity, quantity_total)'),
            'client_company' => DB::raw('COALESCE(client_company, delivery_location)'),
            'is_open_bidding' => DB::raw('open_bidding'),
            'version' => DB::raw('COALESCE(version, rfq_version)'),
            'version_no' => DB::raw('COALESCE(version_no, rfq_version)'),
        ]);

        Schema::table('rfqs', function (Blueprint $table): void {
            $table->enum('status', ['awaiting', 'open', 'closed', 'awarded', 'cancelled'])->default('awaiting')->change();
        });

        Schema::table('rfqs', function (Blueprint $table): void {
            $columns = [
                'delivery_location',
                'quantity_total',
                'rfq_version',
                'attachments_count',
                'meta',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('rfqs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if ($this->indexExists('rfqs', 'rfqs_company_status_due_idx')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropIndex('rfqs_company_status_due_idx');
            });
        }

        if ($this->indexExists('rfqs', 'rfqs_company_id_tmp_idx')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropIndex('rfqs_company_id_tmp_idx');
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
