<?php

namespace App\Console\Commands;

use App\Services\Admin\HealthService;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class CopilotRuntimeGatesCommand extends Command
{
    protected $signature = 'copilot:runtime-gates
        {--format=table : Output format (table or json)}
        {--output= : Optional output path for JSON evidence}';

    protected $description = 'Validate Copilot runtime launch gates (AI config, middleware chain, and queue health).';

    public function __construct(
        private readonly Router $router,
        private readonly HealthService $healthService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $checks = [];

        $this->checkAiConfig($checks);
        $this->checkFeatureFlags($checks);
        $this->checkRouteMiddleware($checks);
        $this->checkQueueHealth($checks);
        $this->checkAdminHealthSummary($checks);

        $failures = array_values(array_filter($checks, static fn (array $check): bool => $check['status'] === 'fail'));
        $warnings = array_values(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warn'));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_checks' => count($checks),
                'failed' => count($failures),
                'warnings' => count($warnings),
                'result' => $failures === [] ? 'pass' : 'fail',
            ],
            'checks' => $checks,
        ];

        $this->render($payload, $checks);
        $this->writeOutputIfRequested($payload);

        if ($failures !== []) {
            $this->error(sprintf('Copilot runtime gates failed: %d failure(s), %d warning(s).', count($failures), count($warnings)));

            return self::FAILURE;
        }

        $this->info(sprintf('Copilot runtime gates passed with %d warning(s).', count($warnings)));

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkAiConfig(array &$checks): void
    {
        $enabled = (bool) config('ai.enabled');
        $this->pushCheck(
            $checks,
            'ai.enabled',
            $enabled ? 'pass' : 'fail',
            $enabled,
            $enabled ? 'AI feature is enabled.' : 'Set AI_ENABLED=true in target environment.'
        );

        $baseUrl = trim((string) config('ai.base_url'));
        $baseUrlValid = $baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) !== false;
        $this->pushCheck(
            $checks,
            'ai.base_url',
            $baseUrlValid ? 'pass' : 'fail',
            $baseUrl,
            $baseUrlValid ? 'AI base URL configured.' : 'Set AI_BASE_URL/AI_MICROSERVICE_URL to a valid URL.'
        );

        $sharedSecret = trim((string) config('ai.shared_secret'));
        $secretConfigured = $sharedSecret !== '';
        $this->pushCheck(
            $checks,
            'ai.shared_secret',
            $secretConfigured ? 'pass' : 'fail',
            $secretConfigured ? 'configured' : 'missing',
            $secretConfigured ? 'AI shared secret present.' : 'Set AI_SHARED_SECRET for service auth.'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkFeatureFlags(array &$checks): void
    {
        if (! Schema::hasTable('company_feature_flags')) {
            $this->pushCheck(
                $checks,
                'feature_flags.table',
                'warn',
                'missing',
                'Table company_feature_flags not found; cannot verify tenant flag rollout.'
            );

            return;
        }

        $trackedFlags = [
            'ai_workflows_enabled',
            'ai.copilot',
            'ai_copilot',
            'ai.enabled',
        ];

        $rows = DB::table('company_feature_flags')
            ->whereIn('key', $trackedFlags)
            ->get(['key', 'value']);

        $enabledCount = $rows
            ->filter(function ($row): bool {
                $value = $row->value;

                if (is_bool($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        if (array_key_exists('enabled', $decoded)) {
                            return (bool) $decoded['enabled'];
                        }

                        if (array_key_exists('active', $decoded)) {
                            return (bool) $decoded['active'];
                        }
                    }

                    return trim(strtolower($value)) === 'true';
                }

                if (is_array($value)) {
                    if (array_key_exists('enabled', $value)) {
                        return (bool) $value['enabled'];
                    }

                    if (array_key_exists('active', $value)) {
                        return (bool) $value['active'];
                    }
                }

                return (bool) $value;
            })
            ->count();

        $status = $enabledCount > 0 ? 'pass' : 'warn';

        $this->pushCheck(
            $checks,
            'feature_flags.ai_rollout',
            $status,
            $enabledCount,
            $enabledCount > 0
                ? 'AI Copilot-related feature flags enabled for at least one company.'
                : 'No enabled AI Copilot feature flags found in company_feature_flags.'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkRouteMiddleware(array &$checks): void
    {
        $expectations = [
            'v1/ai/chat/threads' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service', 'ai.rate.limit'],
            'v1/ai/chat/threads/{thread}/send' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service', 'ai.rate.limit'],
            'v1/ai/chat/threads/{thread}/tools/resolve' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service', 'ai.rate.limit'],
            'v1/ai/actions/plan' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service', 'ai.rate.limit'],
            'v1/ai/actions/{draft}/approve' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service'],
            'v1/ai/actions/{draft}/reject' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service'],
            'copilot/search' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service', 'ai.rate.limit'],
            'copilot/answer' => ['auth', 'ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'buyer_access', 'ensure.ai.service', 'ai.rate.limit'],
        ];

        $routesByUri = collect($this->router->getRoutes())
            ->mapWithKeys(fn ($route): array => [$this->normalizeRouteUri($route->uri()) => $route]);

        foreach ($expectations as $uri => $requiredMiddleware) {
            $route = $routesByUri->get($uri);

            if ($route === null) {
                $this->pushCheck(
                    $checks,
                    sprintf('route.%s', $uri),
                    'fail',
                    'missing',
                    'Required Copilot route is not registered.'
                );

                continue;
            }

            $actual = collect($route->gatherMiddleware())
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values()
                ->all();

            $missing = array_values(array_diff($requiredMiddleware, $actual));

            $this->pushCheck(
                $checks,
                sprintf('route.%s', $uri),
                $missing === [] ? 'pass' : 'fail',
                $missing === [] ? 'ok' : implode(', ', $missing),
                $missing === []
                    ? 'Middleware chain covers required launch gates.'
                    : 'Missing required middleware: '.implode(', ', $missing)
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkQueueHealth(array &$checks): void
    {
        $defaultConnection = (string) config('queue.default');
        $this->pushCheck(
            $checks,
            'queue.default',
            $defaultConnection !== '' ? 'pass' : 'fail',
            $defaultConnection,
            $defaultConnection !== '' ? 'Default queue connection configured.' : 'QUEUE_CONNECTION is empty.'
        );

        if ($defaultConnection === 'redis') {
            try {
                $connection = (string) config('queue.connections.redis.connection', 'default');
                $ping = Redis::connection($connection)->ping();
                $ok = $ping !== null;

                $this->pushCheck(
                    $checks,
                    'queue.redis_connectivity',
                    $ok ? 'pass' : 'fail',
                    is_scalar($ping) ? (string) $ping : 'connected',
                    $ok ? 'Redis queue connection reachable.' : 'Redis ping failed for queue connection.'
                );
            } catch (\Throwable $exception) {
                $this->pushCheck(
                    $checks,
                    'queue.redis_connectivity',
                    'fail',
                    'error',
                    $exception->getMessage()
                );
            }
        }

        $jobsTable = (string) config('queue.connections.database.table', 'jobs');
        if (Schema::hasTable($jobsTable)) {
            $pendingJobs = DB::table($jobsTable)->count();
            $status = $pendingJobs > 1000 ? 'warn' : 'pass';

            $this->pushCheck(
                $checks,
                'queue.pending_jobs',
                $status,
                $pendingJobs,
                $pendingJobs > 1000 ? 'High pending job backlog detected.' : 'Pending job backlog is within expected range.'
            );
        } else {
            $this->pushCheck(
                $checks,
                'queue.pending_jobs',
                'warn',
                'n/a',
                sprintf('Jobs table "%s" not found; pending backlog not measurable.', $jobsTable)
            );
        }

        $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');
        if (Schema::hasTable($failedJobsTable)) {
            $recentFailures = DB::table($failedJobsTable)
                ->where('failed_at', '>=', Carbon::now()->subDay())
                ->count();

            $this->pushCheck(
                $checks,
                'queue.failed_jobs_24h',
                $recentFailures === 0 ? 'pass' : 'warn',
                $recentFailures,
                $recentFailures === 0 ? 'No failed jobs in the last 24h.' : 'Investigate recent failed jobs before go-live.'
            );
        } else {
            $this->pushCheck(
                $checks,
                'queue.failed_jobs_24h',
                'warn',
                'n/a',
                sprintf('Failed jobs table "%s" not found; cannot compute recent queue failures.', $failedJobsTable)
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkAdminHealthSummary(array &$checks): void
    {
        $summary = $this->healthService->summary();
        $dbConnected = (bool) ($summary['database_connected'] ?? false);

        $this->pushCheck(
            $checks,
            'admin_health.database_connected',
            $dbConnected ? 'pass' : 'fail',
            $dbConnected,
            $dbConnected ? 'Admin health reports database connectivity.' : 'Admin health reports database connectivity failure.'
        );

        $pendingWebhookDeliveries = (int) ($summary['pending_webhook_deliveries'] ?? 0);
        $this->pushCheck(
            $checks,
            'admin_health.pending_webhook_deliveries',
            $pendingWebhookDeliveries > 100 ? 'warn' : 'pass',
            $pendingWebhookDeliveries,
            $pendingWebhookDeliveries > 100
                ? 'High pending webhook deliveries count; verify async workers.'
                : 'Webhook pending queue within expected range.'
        );
    }

    private function normalizeRouteUri(string $uri): string
    {
        $normalized = ltrim($uri, '/');

        if (str_starts_with($normalized, 'api/')) {
            return ltrim(substr($normalized, 4), '/');
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function pushCheck(array &$checks, string $check, string $status, mixed $value, string $message): void
    {
        $checks[] = [
            'check' => $check,
            'status' => $status,
            'value' => $value,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $checks
     */
    private function render(array $payload, array $checks): void
    {
        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));

            return;
        }

        $rows = array_map(static fn (array $check): array => [
            $check['check'],
            strtoupper((string) $check['status']),
            is_scalar($check['value']) || $check['value'] === null
                ? (string) ($check['value'] ?? 'null')
                : (string) json_encode($check['value']),
            $check['message'],
        ], $checks);

        $this->table(['Check', 'Status', 'Value', 'Message'], $rows);
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
            $this->warn('Failed to encode runtime gate output JSON.');

            return;
        }

        $directory = dirname($output);
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        file_put_contents($output, $encoded);
        $this->info(sprintf('Runtime gate evidence written to %s', $output));
    }
}
