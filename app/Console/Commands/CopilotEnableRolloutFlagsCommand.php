<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use Illuminate\Console\Command;

class CopilotEnableRolloutFlagsCommand extends Command
{
    protected $signature = 'copilot:enable-rollout-flags
        {company_id : Company ID to enable rollout flags for}
        {--flags=ai_workflows_enabled,ai.copilot,ai_copilot,ai.enabled : Comma-separated rollout flag keys}
        {--format=table : Output format (table or json)}
        {--output= : Optional output path for JSON evidence}';

    protected $description = 'Enable tracked Copilot rollout flags in company_feature_flags for a target company.';

    public function handle(): int
    {
        $companyId = (int) $this->argument('company_id');
        if ($companyId < 1) {
            $this->error('company_id must be a positive integer.');

            return self::FAILURE;
        }

        $company = Company::query()->find($companyId);
        if (! $company instanceof Company) {
            $this->error(sprintf('Company %d not found.', $companyId));

            return self::FAILURE;
        }

        $flags = collect(explode(',', (string) $this->option('flags')))
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($flags === []) {
            $this->error('No rollout flag keys were provided.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($flags as $key) {
            $record = CompanyFeatureFlag::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'key' => $key,
                ],
                [
                    'value' => [
                        'enabled' => true,
                        'active' => true,
                        'source' => 'copilot_launch_readiness',
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]
            );

            $rows[] = [
                'company_id' => $companyId,
                'company_name' => (string) $company->name,
                'key' => $key,
                'enabled' => true,
                'record_id' => (int) $record->id,
            ];
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'company' => [
                'id' => $companyId,
                'name' => (string) $company->name,
            ],
            'flags' => $rows,
        ];

        $this->render($payload, $rows);
        $this->writeOutputIfRequested($payload);
        $this->info(sprintf('Enabled %d Copilot rollout flag(s) for company %d.', count($rows), $companyId));

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $rows
     */
    private function render(array $payload, array $rows): void
    {
        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $this->table(
            ['Company ID', 'Company', 'Flag Key', 'Enabled', 'Record ID'],
            array_map(static fn (array $row): array => [
                (int) $row['company_id'],
                (string) $row['company_name'],
                (string) $row['key'],
                ($row['enabled'] ?? false) ? 'true' : 'false',
                (int) $row['record_id'],
            ], $rows)
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeOutputIfRequested(array $payload): void
    {
        $output = (string) $this->option('output');
        if ($output === '') {
            return;
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $this->warn('Failed to encode rollout flag JSON output.');

            return;
        }

        $fullPath = str_starts_with($output, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $output)
            ? $output
            : base_path($output);

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($fullPath, $encoded);
        $this->info(sprintf('Rollout flag output written to %s', $this->toRelativePath($fullPath)));
    }

    private function toRelativePath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $base)) {
            return str_replace('\\', '/', substr($path, strlen($base)));
        }

        return str_replace('\\', '/', $path);
    }
}
