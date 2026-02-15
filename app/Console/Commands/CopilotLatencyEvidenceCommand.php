<?php

namespace App\Console\Commands;

use App\Models\AiEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CopilotLatencyEvidenceCommand extends Command
{
    protected $signature = 'copilot:latency-evidence
        {--hours=24 : Time window in hours}
        {--patterns=ai_chat_*,copilot_*,analytics_copilot : Comma-separated feature wildcard patterns}
        {--format=table : Output format (table or json)}
        {--output= : Optional output path for JSON evidence}';

    protected $description = 'Generate Copilot latency evidence (including p95) from ai_events.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $patterns = collect(explode(',', (string) $this->option('patterns')))
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->values()
            ->all();

        $windowStart = Carbon::now()->subHours($hours);

        $events = AiEvent::query()
            ->whereNotNull('latency_ms')
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->get(['feature', 'latency_ms', 'created_at']);

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
            $this->warn('No matching ai_events latency samples found in the requested time window.');

            return self::FAILURE;
        }

        $allLatencies = $filtered
            ->pluck('latency_ms')
            ->map(static fn ($value): int => max(0, (int) $value))
            ->values()
            ->all();

        $byFeature = $filtered
            ->groupBy('feature')
            ->map(function ($group) {
                $latencies = $group
                    ->pluck('latency_ms')
                    ->map(static fn ($value): int => max(0, (int) $value))
                    ->values()
                    ->all();

                return $this->buildStats($latencies);
            })
            ->sortByDesc('count')
            ->all();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'window' => [
                'hours' => $hours,
                'from' => $windowStart->toIso8601String(),
                'to' => now()->toIso8601String(),
            ],
            'patterns' => $patterns,
            'overall' => $this->buildStats($allLatencies),
            'features' => $byFeature,
        ];

        $this->render($payload, $byFeature);
        $this->writeOutputIfRequested($payload);

        $this->info(sprintf(
            'Latency evidence generated: %d samples, p95=%dms.',
            (int) $payload['overall']['count'],
            (int) $payload['overall']['p95_ms']
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
            ];
        }

        return [
            'count' => $count,
            'min_ms' => min($values),
            'max_ms' => max($values),
            'avg_ms' => round(array_sum($values) / $count, 2),
            'p50_ms' => $this->percentile($values, 0.50),
            'p95_ms' => $this->percentile($values, 0.95),
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
     * @param array<string, array<string, int|float>> $features
     */
    private function render(array $payload, array $features): void
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $this->line(sprintf(
            'Window: last %d hour(s) | Samples: %d | p95: %dms',
            (int) $payload['window']['hours'],
            (int) $payload['overall']['count'],
            (int) $payload['overall']['p95_ms']
        ));

        $rows = [];
        foreach ($features as $feature => $stats) {
            $rows[] = [
                $feature,
                (int) $stats['count'],
                (int) $stats['p50_ms'],
                (int) $stats['p95_ms'],
                (float) $stats['avg_ms'],
                (int) $stats['max_ms'],
            ];
        }

        $this->table(['Feature', 'Count', 'p50 (ms)', 'p95 (ms)', 'Avg (ms)', 'Max (ms)'], $rows);
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
            $this->warn('Failed to encode latency evidence JSON.');

            return;
        }

        $directory = dirname($output);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($output, $encoded);
        $this->info(sprintf('Latency evidence written to %s', $output));
    }
}
