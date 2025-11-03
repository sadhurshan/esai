<?php

namespace App\Support\Schema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseInspector
{
    public function connection(): string
    {
        return DB::connection()->getDriverName();
    }

    public function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * @return array<string, array{name: string, type: string|null, nullable: bool, default: mixed}>
     */
    public function columns(string $table): array
    {
        if (! $this->hasTable($table)) {
            return [];
        }

        return match ($this->connection()) {
            'sqlite' => $this->columnsFromSqlite($table),
            'mysql', 'mariadb' => $this->columnsFromMysql($table),
            'pgsql' => $this->columnsFromPostgres($table),
            default => $this->columnsFromGeneric($table),
        };
    }

    /**
     * @return array<int, array{columns: array<int, string>, type: string, name: string|null}>
     */
    public function indexes(string $table): array
    {
        if (! $this->hasTable($table)) {
            return [];
        }

        return match ($this->connection()) {
            'sqlite' => $this->indexesFromSqlite($table),
            'mysql', 'mariadb' => $this->indexesFromMysql($table),
            'pgsql' => $this->indexesFromPostgres($table),
            default => [],
        };
    }

    /**
     * @return array<string, array{name: string, type: string|null, nullable: bool, default: mixed}>
     */
    protected function columnsFromSqlite(string $table): array
    {
        $rows = DB::select("PRAGMA table_info('".$table."')");

        $columns = [];

        foreach ($rows as $row) {
            $columns[$row->name] = [
                'name' => $row->name,
                'type' => $row->type ? strtolower($row->type) : null,
                'nullable' => $row->notnull === 0,
                'default' => $row->dflt_value,
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, array{name: string, type: string|null, nullable: bool, default: mixed}>
     */
    protected function columnsFromMysql(string $table): array
    {
        $database = DB::getDatabaseName();
        $rows = DB::table('information_schema.columns')
            ->select(['column_name', 'data_type', 'is_nullable', 'column_default'])
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->get();

        $columns = [];

        foreach ($rows as $row) {
            $columns[$row->column_name] = [
                'name' => $row->column_name,
                'type' => $row->data_type ? strtolower($row->data_type) : null,
                'nullable' => strtoupper((string) $row->is_nullable) === 'YES',
                'default' => $row->column_default,
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, array{name: string, type: string|null, nullable: bool, default: mixed}>
     */
    protected function columnsFromPostgres(string $table): array
    {
        $rows = DB::table('information_schema.columns')
            ->select(['column_name', 'data_type', 'is_nullable', 'column_default'])
            ->where('table_name', $table)
            ->get();

        $columns = [];

        foreach ($rows as $row) {
            $columns[$row->column_name] = [
                'name' => $row->column_name,
                'type' => $row->data_type ? strtolower($row->data_type) : null,
                'nullable' => strtoupper((string) $row->is_nullable) === 'YES',
                'default' => $row->column_default,
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, array{name: string, type: string|null, nullable: bool, default: mixed}>
     */
    protected function columnsFromGeneric(string $table): array
    {
        $names = Schema::getColumnListing($table);

        return collect($names)
            ->mapWithKeys(fn ($name) => [$name => [
                'name' => $name,
                'type' => null,
                'nullable' => true,
                'default' => null,
            ]])
            ->all();
    }

    /**
     * @return array<int, array{columns: array<int, string>, type: string, name: string|null}>
     */
    protected function indexesFromSqlite(string $table): array
    {
        $indexes = [];
        $list = DB::select("PRAGMA index_list('".$table."')");

        foreach ($list as $entry) {
            if ($entry->origin === 'pk') {
                continue;
            }

            $indexInfo = DB::select("PRAGMA index_info('".$entry->name."')");
            $columns = array_map(fn ($c) => $c->name, $indexInfo);

            $indexes[] = [
                'columns' => $columns,
                'type' => ((int) $entry->unique) === 1 ? 'unique' : 'index',
                'name' => $entry->name,
            ];
        }

        return $indexes;
    }

    /**
     * @return array<int, array{columns: array<int, string>, type: string, name: string|null}>
     */
    protected function indexesFromMysql(string $table): array
    {
        $database = DB::getDatabaseName();
        $rows = DB::table('information_schema.statistics')
            ->select(['index_name', 'non_unique', 'index_type', 'column_name'])
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            if ($row->index_name === 'PRIMARY') {
                continue;
            }

            $grouped[$row->index_name]['columns'][] = $row->column_name;
            $grouped[$row->index_name]['type'] = strtolower($row->index_type) === 'fulltext'
                ? 'fulltext'
                : (((int) $row->non_unique) === 0 ? 'unique' : 'index');
            $grouped[$row->index_name]['name'] = $row->index_name;
        }

        return array_map(function ($entry) {
            $entry['columns'] = array_values($entry['columns'] ?? []);

            return $entry;
        }, array_values($grouped));
    }

    /**
     * @return array<int, array{columns: array<int, string>, type: string, name: string|null}>
     */
    protected function indexesFromPostgres(string $table): array
    {
        $sql = <<<'SQL'
SELECT
    i.relname AS index_name,
    idx.indisunique AS is_unique,
    am.amname AS index_type,
    array_to_string(array_agg(a.attname ORDER BY array_position(idx.indkey, a.attnum)), ',') AS columns
FROM
    pg_class t
JOIN pg_namespace ns ON ns.oid = t.relnamespace
JOIN pg_index idx ON idx.indrelid = t.oid
JOIN pg_class i ON i.oid = idx.indexrelid
JOIN pg_am am ON am.oid = i.relam
JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(idx.indkey)
WHERE
    t.relname = ?
    AND ns.nspname = 'public'
    AND idx.indisprimary = false
GROUP BY
    i.relname, idx.indisunique, am.amname
SQL;

        $rows = DB::select($sql, [$table]);

        return array_map(function ($row) {
            return [
                'columns' => array_map('trim', explode(',', $row->columns)),
                'type' => $row->index_type === 'GIN' && str_contains(strtolower($row->index_name), 'fulltext')
                    ? 'fulltext'
                    : ($row->is_unique ? 'unique' : 'index'),
                'name' => $row->index_name,
            ];
        }, $rows);
    }

    public function hasIndex(string $table, array $columns, string $type): bool
    {
        $expected = $this->normalizedIndex($columns, $type);

        foreach ($this->indexes($table) as $index) {
            if ($this->normalizedIndex($index['columns'], $index['type']) === $expected) {
                return true;
            }
        }

        return false;
    }

    protected function normalizedIndex(array $columns, string $type): string
    {
        sort($columns);

        return $type.':'.implode(',', $columns);
    }

    public function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}
