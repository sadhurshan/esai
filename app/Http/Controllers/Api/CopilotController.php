<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AnalyticsSnapshotResource;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Models\CopilotPrompt;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\NotificationService;
use App\Support\Permissions\PermissionRegistry;
use App\Services\Ai\AiClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CopilotController extends ApiController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly AiClient $aiClient,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $plan = $company->plan;

        if ($denial = $this->ensureAnalyticsPermission($user, $company)) {
            return $denial;
        }

        if ($plan === null || ! $plan->analytics_enabled) {
            return $this->fail('Analytics not available on current plan.', 403);
        }

        $question = (string) $request->input('query', '');

        if (trim($question) === '') {
            return $this->fail('Query is required.', 422);
        }

        $contextBlocks = $this->normalizeContextBlocks($request->input('context'));
        $rawInputs = $request->input('inputs', []);
        $inputs = is_array($rawInputs) ? $rawInputs : null;

        if ($inputs === null) {
            return $this->fail('Inputs must be an object.', 422, [
                'inputs' => ['Inputs must be provided as an object.'],
            ]);
        }

        [$snapshotMetrics, $toolMetrics] = $this->resolveMetricIntentions($question);
        $metricsDetected = $snapshotMetrics->count() + $toolMetrics->count();

        if ($metricsDetected === 0) {
            return $this->fail('No supported analytics metrics detected in query.', 422);
        }

        $approved = (bool) $request->boolean('copilot_approval');

        if ($metricsDetected > 1 && ! $approved) {
            return $this->fail('Human approval required before running multi-metric analytics query.', 403);
        }

        $snapshotData = [];

        if ($snapshotMetrics->isNotEmpty()) {
            $snapshots = AnalyticsSnapshot::query()
                ->where('company_id', $company->id)
                ->whereIn('type', $snapshotMetrics->all())
                ->orderByDesc('period_start')
                ->get()
                ->groupBy('type')
                ->map(fn ($group) => $group->first())
                ->filter();

            if ($snapshots->isNotEmpty()) {
                $snapshotData = AnalyticsSnapshotResource::collection($snapshots->values())->toArray($request);
            }
        }

        $toolData = [];

        if ($toolMetrics->isNotEmpty()) {
            foreach ($toolMetrics as $metric) {
                try {
                    $toolData[] = match ($metric) {
                        'forecast_spend' => $this->runForecastSpendMetric($user, $company, $inputs, $contextBlocks),
                        'forecast_supplier_performance' => $this->runSupplierPerformanceMetric($user, $company, $inputs, $contextBlocks),
                        'forecast_inventory' => $this->runInventoryForecastMetric($user, $company, $inputs, $contextBlocks),
                        default => null,
                    };
                } catch (ValidationException $exception) {
                    return $this->fail('Invalid analytics inputs.', 422, $exception->errors());
                } catch (RuntimeException $exception) {
                    return $this->fail($exception->getMessage(), 502);
                }
            }

            $toolData = array_values(array_filter($toolData));
        }

        $responseData = array_merge($snapshotData, $toolData);

        if ($responseData === []) {
            return $this->fail('No analytics data available for the requested metrics.', 404);
        }

        $metricsList = array_values(array_unique(array_merge($snapshotMetrics->all(), $toolMetrics->all())));

        $prompt = CopilotPrompt::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'query' => $question,
            'metrics' => $metricsList,
            'response' => $responseData,
            'status' => 'completed',
            'meta' => [
                'source' => 'analytics_copilot',
                'approval_required' => $metricsDetected > 1,
                'approved' => $approved,
            ],
        ]);

        $this->auditLogger->created($prompt, [
            'query' => $prompt->query,
            'metrics' => $metricsList,
        ]);

        $this->notifyAdmins($company, $question, $metricsList);

        return $this->ok(
            $responseData,
            'Analytics copilot results.',
            [
                'query' => $question,
                'metrics' => $metricsList,
            ]
        );
    }

    /**
     * @param list<string> $metrics
     */
    private function notifyAdmins(Company $company, string $question, array $metrics): void
    {
        $recipients = $company->users()
            ->whereIn('role', ['buyer_admin', 'finance'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notificationService->send(
            $recipients,
            'analytics_query',
            'Copilot analytics query executed',
            'A copilot request queried analytics metrics.',
            Company::class,
            $company->id,
            [
                'query' => $question,
                'metrics' => $metrics,
            ]
        );
    }

    private function ensureAnalyticsPermission(User $user, Company $company): ?JsonResponse
    {
        if (! $this->permissionRegistry->userHasAny($user, ['analytics.read'], (int) $company->id)) {
            return $this->fail('Analytics role required.', 403, [
                'code' => 'analytics_role_required',
            ]);
        }

        return null;
    }

    /**
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection}
     */
    private function resolveMetricIntentions(string $question): array
    {
        $keywordMap = [
            'cycle time' => ['source' => 'snapshot', 'metric' => AnalyticsSnapshot::TYPE_CYCLE_TIME],
            'otif' => ['source' => 'snapshot', 'metric' => AnalyticsSnapshot::TYPE_OTIF],
            'response rate' => ['source' => 'snapshot', 'metric' => AnalyticsSnapshot::TYPE_RESPONSE_RATE],
            'forecast accuracy' => ['source' => 'snapshot', 'metric' => AnalyticsSnapshot::TYPE_FORECAST_ACCURACY],
            'spend performance' => ['source' => 'snapshot', 'metric' => AnalyticsSnapshot::TYPE_SPEND],
            'forecast spend' => ['source' => 'tool', 'metric' => 'forecast_spend'],
            'spend forecast' => ['source' => 'tool', 'metric' => 'forecast_spend'],
            'projected spend' => ['source' => 'tool', 'metric' => 'forecast_spend'],
            'supplier performance forecast' => ['source' => 'tool', 'metric' => 'forecast_supplier_performance'],
            'inventory forecast' => ['source' => 'tool', 'metric' => 'forecast_inventory'],
            'forecast inventory' => ['source' => 'tool', 'metric' => 'forecast_inventory'],
        ];

        $lower = Str::lower($question);
        $matches = collect($keywordMap)
            ->filter(static fn ($config, $phrase) => Str::contains($lower, $phrase));

        $snapshotMetrics = $matches
            ->filter(static fn ($config) => $config['source'] === 'snapshot')
            ->pluck('metric')
            ->unique()
            ->values();

        $toolMetrics = $matches
            ->filter(static fn ($config) => $config['source'] === 'tool')
            ->pluck('metric')
            ->unique()
            ->values();

        return [$snapshotMetrics, $toolMetrics];
    }

    /**
     * @param mixed $context
     * @return array<int, array<string, mixed>>
     */
    private function normalizeContextBlocks(mixed $context): array
    {
        if (! is_array($context)) {
            return [];
        }

        $normalized = [];

        foreach ($context as $block) {
            if (! is_array($block)) {
                continue;
            }

            $normalized[] = $block;

            if (count($normalized) >= 5) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<int, array<string, mixed>> $context
     * @throws ValidationException
     */
    private function runForecastSpendMetric(User $user, Company $company, array $inputs, array $context): array
    {
        $category = $this->requireInputString($inputs, 'category', 'Category is required for spend forecasts.');

        $payloadInputs = [
            'category' => $category,
            'past_period_days' => $this->intInput($inputs['past_period_days'] ?? null, 90, 1, 365),
            'projected_period_days' => $this->intInput($inputs['projected_period_days'] ?? null, 30, 1, 365),
        ];

        if (! empty($inputs['drivers'])) {
            $payloadInputs['drivers'] = $this->stringListInput($inputs['drivers']);
        }

        $response = $this->aiClient->forecastSpendTool(array_merge(
            $this->buildToolBasePayload($user, $company, $context),
            ['inputs' => $payloadInputs]
        ));

        if ($response['status'] !== 'success') {
            throw new RuntimeException($response['message'] ?? 'Failed to generate spend forecast.');
        }

        $data = $response['data'] ?? [];
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $summary = is_string($data['summary'] ?? null) && $data['summary'] !== ''
            ? $data['summary']
            : 'Spend forecast generated.';
        $periodDays = (int) ($payload['projected_period_days'] ?? $payloadInputs['projected_period_days']);

        $now = Carbon::now();
        $timestamp = $now->toIso8601String();
        $periodStart = $now->toDateString();
        $periodEnd = $now->copy()->addDays(max(1, $periodDays))->toDateString();

        return [
            'type' => 'forecast_spend',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'value' => (float) ($payload['projected_total'] ?? 0),
            'meta' => [
                'summary' => $summary,
                'payload' => $payload,
                'citations' => $data['citations'] ?? [],
            ],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<int, array<string, mixed>> $context
     * @throws ValidationException
     */
    private function runSupplierPerformanceMetric(User $user, Company $company, array $inputs, array $context): array
    {
        $supplierId = $this->requireInputString($inputs, 'supplier_id', 'Supplier ID is required for supplier performance forecasts.');

        $payloadInputs = [
            'supplier_id' => $supplierId,
            'period_days' => $this->intInput($inputs['period_days'] ?? null, 30, 1, 180),
        ];

        $metricName = $this->optionalInputString($inputs, 'metric');
        if ($metricName !== null) {
            $payloadInputs['metric'] = $metricName;
        }

        $response = $this->aiClient->forecastSupplierPerformanceTool(array_merge(
            $this->buildToolBasePayload($user, $company, $context),
            ['inputs' => $payloadInputs]
        ));

        if ($response['status'] !== 'success') {
            throw new RuntimeException($response['message'] ?? 'Failed to generate supplier performance forecast.');
        }

        $data = $response['data'] ?? [];
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $summary = is_string($data['summary'] ?? null) && $data['summary'] !== ''
            ? $data['summary']
            : 'Supplier performance forecast generated.';
        $periodDays = (int) ($payload['period_days'] ?? $payloadInputs['period_days']);

        $now = Carbon::now();
        $timestamp = $now->toIso8601String();
        $periodStart = $now->toDateString();
        $periodEnd = $now->copy()->addDays(max(1, $periodDays))->toDateString();

        return [
            'type' => 'forecast_supplier_performance',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'value' => (float) ($payload['projection'] ?? 0),
            'meta' => [
                'summary' => $summary,
                'payload' => $payload,
                'citations' => $data['citations'] ?? [],
            ],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<int, array<string, mixed>> $context
     * @throws ValidationException
     */
    private function runInventoryForecastMetric(User $user, Company $company, array $inputs, array $context): array
    {
        $itemId = $this->requireInputString($inputs, 'item_id', 'Item ID is required for inventory forecasts.');

        $payloadInputs = [
            'item_id' => $itemId,
            'period_days' => $this->intInput($inputs['period_days'] ?? null, 30, 1, 365),
            'lead_time_days' => $this->intInput($inputs['lead_time_days'] ?? null, 14, 1, 180),
        ];

        $reorderDate = $this->optionalInputString($inputs, 'expected_reorder_date');
        if ($reorderDate !== null) {
            $payloadInputs['expected_reorder_date'] = $reorderDate;
        }

        $response = $this->aiClient->forecastInventoryTool(array_merge(
            $this->buildToolBasePayload($user, $company, $context),
            ['inputs' => $payloadInputs]
        ));

        if ($response['status'] !== 'success') {
            throw new RuntimeException($response['message'] ?? 'Failed to generate inventory forecast.');
        }

        $data = $response['data'] ?? [];
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $summary = is_string($data['summary'] ?? null) && $data['summary'] !== ''
            ? $data['summary']
            : 'Inventory forecast generated.';
        $periodDays = (int) ($payload['period_days'] ?? $payloadInputs['period_days']);

        $now = Carbon::now();
        $timestamp = $now->toIso8601String();
        $periodStart = $now->toDateString();
        $periodEnd = $now->copy()->addDays(max(1, $periodDays))->toDateString();

        return [
            'type' => 'forecast_inventory',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'value' => (float) ($payload['expected_usage'] ?? 0),
            'meta' => [
                'summary' => $summary,
                'payload' => $payload,
                'citations' => $data['citations'] ?? [],
            ],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $context
     * @return array<string, mixed>
     */
    private function buildToolBasePayload(User $user, Company $company, array $context): array
    {
        return [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'context' => $context,
        ];
    }

    /**
     * @param array<string, mixed> $inputs
     * @throws ValidationException
     */
    private function requireInputString(array $inputs, string $key, string $message): string
    {
        $value = $inputs[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            throw ValidationException::withMessages([
                sprintf('inputs.%s', $key) => [$message],
            ]);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                sprintf('inputs.%s', $key) => [$message],
            ]);
        }

        return mb_substr($normalized, 0, 120);
    }

    /**
     * @param array<string, mixed> $inputs
     */
    private function optionalInputString(array $inputs, string $key): ?string
    {
        if (! array_key_exists($key, $inputs)) {
            return null;
        }

        $value = $inputs[$key];

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : mb_substr($normalized, 0, 120);
    }

    private function intInput(mixed $value, int $default, int $min, int $max): int
    {
        $candidate = is_numeric($value) ? (int) $value : $default;

        return max($min, min($candidate, $max));
    }

    private function stringListInput(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $normalized = trim($entry);

            if ($normalized === '') {
                continue;
            }

            $items[] = mb_substr($normalized, 0, 200);

            if (count($items) >= 5) {
                break;
            }
        }

        return $items;
    }
}
