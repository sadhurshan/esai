<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurgeUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('PurgeUsersSeeder can only run in local or testing environments.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver !== 'mysql') {
            throw new RuntimeException(sprintf('PurgeUsersSeeder supports MySQL only, current driver: %s.', $driver));
        }

        $database = $connection->getDatabaseName();
        $tables = $this->resolvePurgeTables($database);
        $protectedUserIds = $this->resolveProtectedUserIds();
        $protectedCompanyIds = $this->resolveProtectedCompanyIds($protectedUserIds);
        $protectedEmails = $this->resolveProtectedEmails($protectedUserIds);
        $columnsByTable = $this->resolveTableColumns($tables, $database);

        if ($tables === []) {
            $this->command?->warn('No user or company data tables found to purge.');

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $columns = $columnsByTable[$table] ?? [];

                if ($table === 'companies') {
                    $this->purgeCompanies($connection, $protectedCompanyIds);

                    continue;
                }

                if ($table === 'password_reset_tokens' && in_array('email', $columns, true)) {
                    $this->purgePasswordResetTokens($connection, $protectedEmails);

                    continue;
                }

                if ($table === 'personal_access_tokens'
                    && in_array('tokenable_type', $columns, true)
                    && in_array('tokenable_id', $columns, true)) {
                    $this->purgePersonalAccessTokens($connection, $protectedUserIds);

                    continue;
                }

                if (in_array('company_id', $columns, true) && in_array('user_id', $columns, true)) {
                    $this->purgeByCompanyAndUser($connection, $table, $protectedCompanyIds, $protectedUserIds);

                    continue;
                }

                if (in_array('company_id', $columns, true)) {
                    $this->purgeByCompany($connection, $table, $protectedCompanyIds);

                    continue;
                }

                if (in_array('user_id', $columns, true)) {
                    $this->purgeByUser($connection, $table, $protectedUserIds);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info(sprintf('Purged %d tables: %s', count($tables), implode(', ', $tables)));
    }

    /**
     * @return array<int, string>
     */
    private function resolvePurgeTables(string $database): array
    {
        $tables = DB::table('information_schema.columns')
            ->select('table_name')
            ->where('table_schema', $database)
            ->whereIn('column_name', ['company_id', 'user_id'])
            ->pluck('table_name')
            ->all();

        $tables = array_merge($tables, $this->extraUserTables());
        $tables = array_values(array_unique($tables));
        sort($tables);

        return $tables;
    }

    /**
     * @return array<int, string>
     */
    private function extraUserTables(): array
    {
        return [
            'companies',
            'company_user',
            'password_reset_tokens',
            'personal_access_tokens',
            'platform_admins',
            'sessions',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveTableColumns(array $tables, string $database): array
    {
        if ($tables === []) {
            return [];
        }

        $rows = DB::table('information_schema.columns')
            ->select('table_name', 'column_name')
            ->where('table_schema', $database)
            ->whereIn('table_name', $tables)
            ->get();

        $columnsByTable = [];

        foreach ($rows->groupBy('table_name') as $table => $columns) {
            $columnsByTable[$table] = $columns->pluck('column_name')->all();
        }

        return $columnsByTable;
    }

    /**
     * @return array<int, int>
     */
    private function resolveProtectedUserIds(): array
    {
        $userIds = DB::table('users')
            ->where('role', 'platform_super')
            ->pluck('id')
            ->all();

        if (Schema::hasTable('platform_admins')) {
            $platformIds = DB::table('platform_admins')
                ->where('enabled', true)
                ->where('role', 'super')
                ->pluck('user_id')
                ->all();

            $userIds = array_merge($userIds, $platformIds);
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        sort($userIds);

        return $userIds;
    }

    /**
     * @param array<int, int> $protectedUserIds
     * @return array<int, int>
     */
    private function resolveProtectedCompanyIds(array $protectedUserIds): array
    {
        if ($protectedUserIds === []) {
            return [];
        }

        $companyIds = DB::table('companies')
            ->whereIn('owner_user_id', $protectedUserIds)
            ->pluck('id')
            ->all();

        $userCompanyIds = DB::table('users')
            ->whereIn('id', $protectedUserIds)
            ->pluck('company_id')
            ->all();

        $companyUserIds = DB::table('company_user')
            ->whereIn('user_id', $protectedUserIds)
            ->pluck('company_id')
            ->all();

        $companyIds = array_merge($companyIds, $userCompanyIds, $companyUserIds);
        $companyIds = array_filter($companyIds, static fn ($id) => $id !== null);
        $companyIds = array_values(array_unique(array_map('intval', $companyIds)));
        sort($companyIds);

        return $companyIds;
    }

    /**
     * @param array<int, int> $protectedUserIds
     * @return array<int, string>
     */
    private function resolveProtectedEmails(array $protectedUserIds): array
    {
        if ($protectedUserIds === []) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $protectedUserIds)
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }

    private function purgeCompanies($connection, array $protectedCompanyIds): void
    {
        if ($protectedCompanyIds === []) {
            $connection->table('companies')->delete();

            return;
        }

        $connection->table('companies')
            ->whereNotIn('id', $protectedCompanyIds)
            ->delete();
    }

    private function purgeByCompany($connection, string $table, array $protectedCompanyIds): void
    {
        $query = $connection->table($table);

        if ($protectedCompanyIds !== []) {
            $query->whereNotIn('company_id', $protectedCompanyIds);
        }

        $query->delete();
    }

    private function purgeByUser($connection, string $table, array $protectedUserIds): void
    {
        $query = $connection->table($table);

        if ($protectedUserIds !== []) {
            $query->whereNotIn('user_id', $protectedUserIds);
        }

        $query->delete();
    }

    private function purgeByCompanyAndUser(
        $connection,
        string $table,
        array $protectedCompanyIds,
        array $protectedUserIds
    ): void {
        $query = $connection->table($table);

        if ($protectedCompanyIds === [] && $protectedUserIds === []) {
            $query->delete();

            return;
        }

        if ($protectedCompanyIds === []) {
            $query->whereNotIn('user_id', $protectedUserIds)->delete();

            return;
        }

        if ($protectedUserIds === []) {
            $query->whereNotIn('company_id', $protectedCompanyIds)->delete();

            return;
        }

        $query
            ->whereNotIn('company_id', $protectedCompanyIds)
            ->whereNotIn('user_id', $protectedUserIds)
            ->delete();
    }

    private function purgePasswordResetTokens($connection, array $protectedEmails): void
    {
        $query = $connection->table('password_reset_tokens');

        if ($protectedEmails !== []) {
            $query->whereNotIn('email', $protectedEmails);
        }

        $query->delete();
    }

    private function purgePersonalAccessTokens($connection, array $protectedUserIds): void
    {
        $query = $connection->table('personal_access_tokens')
            ->where('tokenable_type', User::class);

        if ($protectedUserIds !== []) {
            $query->whereNotIn('tokenable_id', $protectedUserIds);
        }

        $query->delete();
    }
}
