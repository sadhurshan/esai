<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMySql() || ! Schema::hasTable('parts')) {
            return;
        }

        Schema::table('parts', function (Blueprint $table): void {
            if ($this->indexExists('parts', 'parts_name_number_fulltext')) {
                $table->dropFullText('parts_name_number_fulltext');
            }

            if (! $this->indexExists('parts', 'parts_search_fulltext')) {
                $table->fullText(['part_number', 'name', 'spec'], 'parts_search_fulltext');
            }
        });
    }

    public function down(): void
    {
        if (! $this->isMySql() || ! Schema::hasTable('parts')) {
            return;
        }

        Schema::table('parts', function (Blueprint $table): void {
            if ($this->indexExists('parts', 'parts_search_fulltext')) {
                $table->dropFullText('parts_search_fulltext');
            }

            if (! $this->indexExists('parts', 'parts_name_number_fulltext')) {
                $table->fullText(['name', 'part_number'], 'parts_name_number_fulltext');
            }
        });
    }

    private function isMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
