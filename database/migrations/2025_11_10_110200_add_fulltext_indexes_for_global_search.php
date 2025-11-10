<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        if ($this->columnExists('companies', 'name') && ! $this->indexExists('companies', 'companies_name_fulltext')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->fullText('name', 'companies_name_fulltext');
            });
        }

        if ($this->columnExists('suppliers', 'name') && ! $this->indexExists('suppliers', 'suppliers_name_fulltext')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->fullText('name', 'suppliers_name_fulltext');
            });
        }

        if ($this->columnExists('parts', 'name') && $this->columnExists('parts', 'part_number')) {
            if (! $this->indexExists('parts', 'parts_name_number_fulltext')) {
                Schema::table('parts', function (Blueprint $table): void {
                    $table->fullText(['name', 'part_number'], 'parts_name_number_fulltext');
                });
            }
        } else {
            // TODO: clarify with spec - parts table not present, full-text index deferred until module ships.
        }

        if ($this->columnExists('rfqs', 'title') && $this->columnExists('rfqs', 'number') && ! $this->indexExists('rfqs', 'rfqs_title_number_fulltext')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->fullText(['title', 'number'], 'rfqs_title_number_fulltext');
            });
        }

        if ($this->columnExists('purchase_orders', 'po_number') && ! $this->indexExists('purchase_orders', 'purchase_orders_number_fulltext')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->fullText('po_number', 'purchase_orders_number_fulltext');
            });
        }

        if ($this->columnExists('invoices', 'invoice_number') && ! $this->indexExists('invoices', 'invoices_number_fulltext')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->fullText('invoice_number', 'invoices_number_fulltext');
            });
        }

        if ($this->columnExists('documents', 'filename') && ! $this->indexExists('documents', 'documents_filename_fulltext')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->fullText('filename', 'documents_filename_fulltext');
            });
        }
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        if ($this->indexExists('companies', 'companies_name_fulltext')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropFullText('companies_name_fulltext');
            });
        }

        if ($this->indexExists('suppliers', 'suppliers_name_fulltext')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropFullText('suppliers_name_fulltext');
            });
        }

        if ($this->indexExists('parts', 'parts_name_number_fulltext')) {
            Schema::table('parts', function (Blueprint $table): void {
                $table->dropFullText('parts_name_number_fulltext');
            });
        }

        if ($this->indexExists('rfqs', 'rfqs_title_number_fulltext')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropFullText('rfqs_title_number_fulltext');
            });
        }

        if ($this->indexExists('purchase_orders', 'purchase_orders_number_fulltext')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->dropFullText('purchase_orders_number_fulltext');
            });
        }

        if ($this->indexExists('invoices', 'invoices_number_fulltext')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropFullText('invoices_number_fulltext');
            });
        }

        if ($this->indexExists('documents', 'documents_filename_fulltext')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->dropFullText('documents_filename_fulltext');
            });
        }
    }

    private function isMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
