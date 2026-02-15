<?php

namespace App\Console\Commands;

use App\Models\AiEvent;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CopilotLatencyBreakdownCommand extends Command
{
    protected $signature = 'copilot:latency-breakdown
        {--hours=168 : Time window in hours}
        {--patterns=ai_chat_*,copilot_*,analytics_copilot : Comma-separated feature wildcard patterns}
        {--top=10 : Number of rows in top lists}
        {--min-samples=3 : Minimum samples for grouped stats}
        {--format=table : Output format (table or json)}
        {--output= : Optional output path for JSON evidence}';

    protected $description = 'Generate detailed Copilot latency breakdown by feature and company from ai_events.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $top = max(1, (int) $this->option('top'));
        $minSamples = max(1, (int) $this->option('min-samples'));
        $patterns = collect(explode(',', (string) $this->option('patterns')))
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->values()
            ->all();

        $from = Carbon::now()->subHours($hours);

        $events = AiEvent::query()
            ->whereNotNull('latency_ms')
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')
            ->get(['id', 'company_id', 'feature', 'status', 'latency_ms', 'created_at']);

        $filtered = $events->filter(function (AiEvent $event) use ($patterns): bool {
            $feature = (string) ($event->feature ?? '');
            if ($feature === '') {
                return false;
            }

            if ($patterns === []) {
                return true;
            }

            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $feature)) {
                    return true;
                }
            }

            return false;
        })->values();

        if ($filtered->isEmpty()) {
            $this->warn('No matching ai_events found for the requested latency breakdown.');

            return self::FAILURE;
        }

        $companyIds = $filtered
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $companyNames = Company::query()
            ->whereIn('id', $companyIds)
            ->pluck('name', 'id');

        $overall = $this->buildStats($filtered->pluck('latency_ms')->map(static fn ($value): int => max(0, (int) $value))->all());

        $byFeature = $filtered
            ->groupBy('feature')
            ->map(function ($group, $feature) {
                $stats = $this->buildStats($group->pluck('latency_ms')->map(static fn ($value): int => max(0, (int) $value))->all());

                return array_merge(['feature' => (string) $feature], $stats);
            })
            ->filter(fn (array $stats): bool => (int) $stats['count'] >= $minSamples)
            ->sortByDesc('p95_ms')
            ->values()
            ->take($top)
            ->all();

        $byCompanyFeature = $filtered
            ->groupBy(fn (AiEvent $event): string => sprintf('%s::%s', (string) $event->company_id, (string) $event->feature))
            ->map(function ($group, string $key) use ($companyNames) {
                [$companyIdRaw, $feature] = explode('::', $key, 2);
                $companyId = (int) $companyIdRaw;
                $stats = $this->buildStats($group->pluck('latency_ms')->map(static fn ($value): int => max(0, (int) $value))->all());

                return array_merge([
                    'company_id' => $companyId,
                    'company_name' => $companyNames->get($companyId),
                    'feature' => $feature,
                ], $stats);
            })
            ->filter(fn (array $stats): bool => (int) $stats['count'] >= $minSamples)
            ->sortByDesc('p95_ms')
            ->values()
            ->take($top)
            ->all();

        $slowestSamples = $filtered
            ->sortByDesc('latency_ms')
            ->take($top)
            ->map(function (AiEvent $event) use ($companyNames): array {
                return [
                    'id' => (int) $event->id,
                    'company_id' => (int) $event->company_id,
                    'company_name' => $companyNames->get((int) $event->company_id),
                    'feature' => (string) $event->feature,
                    'status' => (string) $event->status,
                    'latency_ms' => (int) $event->latency_ms,
                    'created_at' => optional($event->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'window' => [
                'hours' => $hours,
                'from' => $from->toIso8601String(),
                'to' => now()->toIso8601String(),
            ],
            'patterns' => $patterns,
            'overall' => $overall,
            'top_by_feature_p95' => $byFeature,
            'top_by_company_feature_p95' => $byCompanyFeature,
            'slowest_samples' => $slowestSamples,
        ];

        $this->render($payload, $byFeature, $byCompanyFeature, $slowestSamples);
        $this->writeOutputIfRequested($payload);

        $this->info(sprintf(
            'Latency breakdown generated: samples=%d, overall p95=%dms, overall p99=%dms.',
            (int) ($overall['count'] ?? 0),
            (int) ($overall['p95_ms'] ?? 0),
            (int) ($overall['p99_ms'] ?? 0)
        ));

        return self::SUCCESS;
    }

    /**
     * @param array<int, int> $values
     * @return array<string, int|float>
     */
    private function buildStats(array $values): array
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return [
                'count' => 0,
                'min_ms' => 0,
                'max_ms' => 0,
                'avg_ms' => 0,
                'p50_ms' => 0,
                'p95_ms' => 0,
                'p99_ms' => 0,
            ];
        }

        return [
            'count' => $count,
            'min_ms' => min($values),
            'max_ms' => max($values),
            'avg_ms' => round(array_sum($values) / $count, 2),
            'p50_ms' => $this->percentile($values, 0.50),
            'p95_ms' => $this->percentile($values, 0.95),
            'p99_ms' => $this->percentile($values, 0.99),
        ];
    }

    /**
     * @param array<int, int> $sortedValues
     */
    private function percentile(array $sortedValues, float $percentile): int
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0;
        }

        $index = max(0, (int) ceil($percentile * $count) - 1);

        return $sortedValues[$index] ?? end($sortedValues) ?: 0;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $byFeature
     * @param array<int, array<string, mixed>> $byCompanyFeature
     * @param array<int, array<string, mixed>> $slowestSamples
     */
    private function render(array $payload, array $byFeature, array $byCompanyFeature, array $slowestSamples): void
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $overall = $payload['overall'];
        $this->line(sprintf(
            'Overall: samples=%d p50=%dms p95=%dms p99=%dms avg=%.2fms',
            (int) $overall['count'],
            (int) $overall['p50_ms'],
            (int) $overall['p95_ms'],
            (int) $overall['p99_ms'],
            (float) $overall['avg_ms']
        ));

        $this->table(
            ['Feature', 'Count', 'p95 (ms)', 'p99 (ms)', 'Avg (ms)'],
            array_map(static fn (array $row): array => [
                (string) $row['feature'],
                (int) $row['count'],
                (int) $row['p95_ms'],
                (int) $row['p99_ms'],
                (float) $row['avg_ms'],
            ], $byFeature)
        );

        $this->table(
            ['Company', 'Feature', 'Count', 'p95 (ms)', 'p99 (ms)'],
            array_map(static fn (array $row): array => [
                (string) ($row['company_name'] ?? ('#'.(int) $row['company_id'])),
                (string) $row['feature'],
                (int) $row['count'],
                (int) $row['p95_ms'],
                (int) $row['p99_ms'],
            ], $byCompanyFeature)
        );

        $this->table(
            ['Event ID', 'Company', 'Feature', 'Status', 'Latency (ms)', 'Created At'],
            array_map(static fn (array $row): array => [
                (int) $row['id'],
                (string) ($row['company_name'] ?? ('#'.(int) $row['company_id'])),
                (string) $row['feature'],
                (string) $row['status'],
                (int) $row['latency_ms'],
                (string) $row['created_at'],
            ], $slowestSamples)
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
            $this->warn('Failed to encode latency breakdown JSON.');

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
        $this->info(sprintf('Latency breakdown written to %s', $this->toRelativePath($fullPath)));
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
