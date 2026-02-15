<?php

namespace App\Console\Commands;

use App\Models\AiEvent;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CopilotLatencyTraceAttributionCommand extends Command
{
    protected $signature = 'copilot:latency-trace-attribution
        {--hours=168 : Time window in hours}
        {--patterns=ai_chat_*,copilot_*,analytics_copilot : Comma-separated feature wildcard patterns}
        {--top=10 : Number of slow samples to analyze}
        {--format=table : Output format (table or json)}
        {--output= : Optional output path for JSON evidence}';

    protected $description = 'Extract phase-level timing signals from Copilot ai_events payloads for slow-latency attribution.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $top = max(1, (int) $this->option('top'));
        $patterns = collect(explode(',', (string) $this->option('patterns')))
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->values()
            ->all();

        $from = Carbon::now()->subHours($hours);

        $events = AiEvent::query()
            ->where('created_at', '>=', $from)
            ->orderByDesc('latency_ms')
            ->get(['id', 'company_id', 'feature', 'status', 'latency_ms', 'request_json', 'response_json', 'created_at']);

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
            $this->warn('No matching ai_events found for latency trace attribution.');

            return self::FAILURE;
        }

        $samples = $filtered->take($top)->values();

        $companyIds = $samples
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $companyNames = Company::query()
            ->whereIn('id', $companyIds)
            ->pluck('name', 'id');

        $fieldValues = [];
        $sampleRows = [];
        $samplesWithSignals = 0;

        foreach ($samples as $event) {
            $signals = $this->extractTimingSignals($event);

            if ($signals !== []) {
                $samplesWithSignals++;
            }

            foreach ($signals as $path => $value) {
                $fieldValues[$path] ??= [];
                $fieldValues[$path][] = $value;
            }

            $sampleRows[] = [
                'id' => (int) $event->id,
                'company_id' => (int) $event->company_id,
                'company_name' => $companyNames->get((int) $event->company_id),
                'feature' => (string) $event->feature,
                'status' => (string) $event->status,
                'latency_ms' => $event->latency_ms !== null ? (int) $event->latency_ms : null,
                'created_at' => optional($event->created_at)?->toIso8601String(),
                'timing_signals' => $signals,
            ];
        }

        $fieldStats = collect($fieldValues)
            ->map(function (array $values, string $path): array {
                sort($values);
                $count = count($values);

                return [
                    'path' => $path,
                    'count' => $count,
                    'min_ms' => $count > 0 ? min($values) : 0,
                    'max_ms' => $count > 0 ? max($values) : 0,
                    'avg_ms' => $count > 0 ? round(array_sum($values) / $count, 2) : 0,
                    'p95_ms' => $this->percentile($values, 0.95),
                ];
            })
            ->sortByDesc('p95_ms')
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
            'sample_size' => count($sampleRows),
            'timing_signal_coverage' => [
                'samples_with_signals' => $samplesWithSignals,
                'samples_without_signals' => count($sampleRows) - $samplesWithSignals,
                'coverage_ratio' => count($sampleRows) > 0
                    ? round($samplesWithSignals / count($sampleRows), 4)
                    : 0,
            ],
            'field_stats' => $fieldStats,
            'slow_samples' => $sampleRows,
            'notes' => $samplesWithSignals === 0
                ? ['No nested phase timing fields were detected in sampled ai_events payloads; total latency instrumentation exists, but phase-level instrumentation appears missing.']
                : [],
        ];

        $this->render($payload, $fieldStats, $sampleRows);
        $this->writeOutputIfRequested($payload);

        $this->info(sprintf(
            'Trace attribution generated: sampled=%d, timing-signals=%d.',
            (int) $payload['sample_size'],
            (int) $payload['timing_signal_coverage']['samples_with_signals']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function extractTimingSignals(AiEvent $event): array
    {
        $signals = [];

        $request = is_array($event->request_json) ? $event->request_json : [];
        $response = is_array($event->response_json) ? $event->response_json : [];

        $this->collectTimingSignals($request, 'request', $signals);
        $this->collectTimingSignals($response, 'response', $signals);

        return $signals;
    }

    /**
     * @param array<string, mixed>|list<mixed> $value
     * @param array<string, int> $signals
     */
    private function collectTimingSignals(array $value, string $prefix, array &$signals, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }

        foreach ($value as $key => $entry) {
            $keyString = is_string($key) ? $key : (string) $key;
            $path = $prefix === '' ? $keyString : sprintf('%s.%s', $prefix, $keyString);

            if (is_array($entry)) {
                $this->collectTimingSignals($entry, $path, $signals, $depth + 1);

                continue;
            }

            if (! is_numeric($entry)) {
                continue;
            }

            if (! $this->looksLikeTimingPath($path)) {
                continue;
            }

            $signals[$path] = max(0, (int) round((float) $entry));
        }
    }

    private function looksLikeTimingPath(string $path): bool
    {
        $normalized = strtolower($path);

        return str_contains($normalized, 'latency')
            || str_contains($normalized, 'duration')
            || str_contains($normalized, 'elapsed')
            || str_contains($normalized, '_ms')
            || str_ends_with($normalized, '.ms')
            || str_contains($normalized, '.ms.');
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
     * @param array<int, array<string, mixed>> $fieldStats
     * @param array<int, array<string, mixed>> $slowSamples
     */
    private function render(array $payload, array $fieldStats, array $slowSamples): void
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $coverage = $payload['timing_signal_coverage'];
        $this->line(sprintf(
            'Sampled: %d | With timing signals: %d | Coverage: %.2f%%',
            (int) $payload['sample_size'],
            (int) $coverage['samples_with_signals'],
            ((float) $coverage['coverage_ratio']) * 100
        ));

        $this->table(
            ['Field path', 'Count', 'p95 (ms)', 'Avg (ms)', 'Max (ms)'],
            array_map(static fn (array $row): array => [
                (string) $row['path'],
                (int) $row['count'],
                (int) $row['p95_ms'],
                (float) $row['avg_ms'],
                (int) $row['max_ms'],
            ], $fieldStats)
        );

        $this->table(
            ['Event ID', 'Company', 'Feature', 'Latency (ms)', 'Signals'],
            array_map(static function (array $row): array {
                $signals = is_array($row['timing_signals']) ? $row['timing_signals'] : [];
                $summary = $signals === []
                    ? '(none)'
                    : implode(', ', array_map(
                        static fn ($path, $value): string => sprintf('%s=%d', (string) $path, (int) $value),
                        array_keys($signals),
                        array_values($signals)
                    ));

                return [
                    (int) $row['id'],
                    (string) ($row['company_name'] ?? ('#'.(int) $row['company_id'])),
                    (string) $row['feature'],
                    $row['latency_ms'] !== null ? (int) $row['latency_ms'] : '(null)',
                    Str::limit($summary, 120),
                ];
            }, $slowSamples)
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
            $this->warn('Failed to encode trace attribution JSON.');

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
        $this->info(sprintf('Trace attribution written to %s', $this->toRelativePath($fullPath)));
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
