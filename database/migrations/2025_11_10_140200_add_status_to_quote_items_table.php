<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('quote_items', 'status')) {
                $table->enum('status', ['pending', 'awarded', 'lost'])->default('pending')->after('note');
                $table->index('status', 'quote_items_status_index');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('quote_items', 'status')) {
            Schema::table('quote_items', function (Blueprint $table): void {
                if ($this->indexExists('quote_items', 'quote_items_status_index')) {
                    $table->dropIndex('quote_items_status_index');
                }
                $table->dropColumn('status');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'sqlite') {
            $results = Schema::getConnection()->select("PRAGMA index_list('".$table."')");

            foreach ($results as $result) {
                if ((string) ($result->name ?? '') === $index) {
                    return true;
                }
            }

            return false;
        }

        if (in_array($connection, ['mysql', 'mariadb'], true)) {
            $database = Schema::getConnection()->getDatabaseName();

            return DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($connection === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        return false;
    }
};
