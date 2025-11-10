<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AnalyticsSnapshotResource;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Models\CopilotPrompt;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CopilotController extends ApiController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
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

        if ($plan === null || ! $plan->analytics_enabled) {
            return $this->fail('Analytics not available on current plan.', 403);
        }

        $question = (string) $request->input('query', '');

        if (trim($question) === '') {
            return $this->fail('Query is required.', 422);
        }

        $keywords = [
            'cycle time' => AnalyticsSnapshot::TYPE_CYCLE_TIME,
            'otif' => AnalyticsSnapshot::TYPE_OTIF,
            'response rate' => AnalyticsSnapshot::TYPE_RESPONSE_RATE,
            'spend' => AnalyticsSnapshot::TYPE_SPEND,
            'forecast accuracy' => AnalyticsSnapshot::TYPE_FORECAST_ACCURACY,
        ];

        $lower = Str::lower($question);

        $metrics = collect($keywords)
            ->filter(fn ($type, $phrase) => Str::contains($lower, $phrase))
            ->values()
            ->unique()
            ->values();

        if ($metrics->isEmpty()) {
            return $this->fail('No supported analytics metrics detected in query.', 422);
        }

        $approved = (bool) $request->boolean('copilot_approval');

        if ($metrics->count() > 1 && ! $approved) {
            return $this->fail('Human approval required before running multi-metric analytics query.', 403);
        }

        $snapshots = AnalyticsSnapshot::query()
            ->where('company_id', $company->id)
            ->whereIn('type', $metrics->all())
            ->orderByDesc('period_start')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->first())
            ->filter();

        if ($snapshots->isEmpty()) {
            return $this->fail('No analytics data available for the requested metrics.', 404);
        }

        $responseData = AnalyticsSnapshotResource::collection($snapshots->values())->toArray($request);

        $prompt = CopilotPrompt::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'query' => $question,
            'metrics' => $metrics->all(),
            'response' => $responseData,
            'status' => 'completed',
            'meta' => [
                'source' => 'analytics_copilot',
                'approval_required' => $metrics->count() > 1,
                'approved' => $approved,
            ],
        ]);

        $this->auditLogger->created($prompt, [
            'query' => $prompt->query,
            'metrics' => $metrics->all(),
        ]);

        $this->notifyAdmins($company, $question, $metrics->all());

        return $this->ok(
            $responseData,
            'Analytics copilot results.',
            [
                'query' => $question,
                'metrics' => $metrics->all(),
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
}
