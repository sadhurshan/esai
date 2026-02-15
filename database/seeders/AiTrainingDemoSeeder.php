<?php

namespace Database\Seeders;

use App\Models\ModelTrainingJob;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiTrainingDemoSeeder extends Seeder
{
    private const BASE_FEATURES = [
        ModelTrainingJob::FEATURE_FORECAST,
        ModelTrainingJob::FEATURE_RISK,
        ModelTrainingJob::FEATURE_RAG,
        ModelTrainingJob::FEATURE_ACTIONS,
        ModelTrainingJob::FEATURE_WORKFLOWS,
        ModelTrainingJob::FEATURE_CHAT,
    ];

    public function run(): void
    {
        // Ensure prior running jobs do not appear in the demo snapshot.
        ModelTrainingJob::query()
            ->where('status', ModelTrainingJob::STATUS_RUNNING)
            ->delete();

        $companyIds = DB::table('companies')
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->all();

        if ($companyIds === []) {
            return;
        }

        $now = Carbon::now();
        $offset = 0;

        foreach (self::BASE_FEATURES as $feature) {
            $this->createJob(
                $feature,
                ModelTrainingJob::STATUS_COMPLETED,
                $companyIds[$offset % count($companyIds)],
                $now,
                $offset,
                $this->metricsForFeature($feature),
                null,
                12,
            );
            $offset++;
        }

        $pendingFeatures = [
            ModelTrainingJob::FEATURE_CHAT,
            ModelTrainingJob::FEATURE_WORKFLOWS,
        ];

        foreach ($pendingFeatures as $feature) {
            $this->createJob(
                $feature,
                ModelTrainingJob::STATUS_PENDING,
                $companyIds[$offset % count($companyIds)],
                $now,
                $offset,
                null,
                null,
                null,
            );
            $offset++;
        }

        $failedFeatures = [
            ModelTrainingJob::FEATURE_RISK,
            ModelTrainingJob::FEATURE_ACTIONS,
        ];

        foreach ($failedFeatures as $feature) {
            $this->createJob(
                $feature,
                ModelTrainingJob::STATUS_FAILED,
                $companyIds[$offset % count($companyIds)],
                $now,
                $offset,
                null,
                'Remote trainer timed out.',
                8,
            );
            $offset++;
        }

        for ($i = 0; $i < 8; $i++) {
            $feature = Arr::random(self::BASE_FEATURES);
            $companyId = $companyIds[($offset + $i) % count($companyIds)];
            $createdAt = (clone $now)->subHours(($offset + $i) * 2);
            $startedAt = (clone $createdAt)->addMinutes(4);
            $finishedAt = (clone $startedAt)->addMinutes(6 + $i);

            ModelTrainingJob::factory()
                ->state([
                    'company_id' => $companyId,
                    'feature' => $feature,
                    'status' => ModelTrainingJob::STATUS_COMPLETED,
                    'microservice_job_id' => 'job_' . Str::lower(Str::random(10)),
                    'parameters_json' => $this->parametersForFeature($feature),
                    'result_json' => $this->metricsForFeature($feature),
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $finishedAt,
                ])
                ->create();
        }
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private function createJob(
        string $feature,
        string $status,
        int $companyId,
        Carbon $now,
        int $offset,
        ?array $result,
        ?string $error,
        ?int $durationMinutes,
    ): void {
        $createdAt = (clone $now)->subHours($offset * 2);
        $startedAt = null;
        $finishedAt = null;

        if ($status !== ModelTrainingJob::STATUS_PENDING) {
            $startedAt = (clone $createdAt)->addMinutes(5);
        }

        if (in_array($status, [ModelTrainingJob::STATUS_COMPLETED, ModelTrainingJob::STATUS_FAILED], true)) {
            $finishedAt = (clone $startedAt)->addMinutes($durationMinutes ?? 10);
        }

        ModelTrainingJob::query()->create([
            'company_id' => $companyId,
            'feature' => $feature,
            'status' => $status,
            'microservice_job_id' => $this->buildMicroserviceJobId($status),
            'parameters_json' => $this->parametersForFeature($feature),
            'result_json' => $result,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'error_message' => $error,
            'created_at' => $createdAt,
            'updated_at' => $finishedAt ?? $startedAt ?? $createdAt,
        ]);
    }

    private function buildMicroserviceJobId(string $status): ?string
    {
        if ($status === ModelTrainingJob::STATUS_PENDING) {
            return null;
        }

        return 'job_' . Str::lower(Str::random(10));
    }

    /**
     * @return array<string, mixed>
     */
    private function parametersForFeature(string $feature): array
    {
        return match ($feature) {
            ModelTrainingJob::FEATURE_FORECAST => [
                'horizon' => 30,
                'window_days' => 90,
                'reindex_all' => false,
            ],
            ModelTrainingJob::FEATURE_RISK => [
                'risk_floor' => 0.12,
                'review_samples' => 640,
            ],
            ModelTrainingJob::FEATURE_RAG => [
                'documents' => 14250,
                'reindex_all' => true,
            ],
            ModelTrainingJob::FEATURE_ACTIONS => [
                'playbooks' => 42,
                'heuristics' => 76,
            ],
            ModelTrainingJob::FEATURE_WORKFLOWS => [
                'templates' => 14,
                'guardrails' => 7,
            ],
            ModelTrainingJob::FEATURE_CHAT => [
                'samples' => 380,
                'redactions' => 12,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsForFeature(string $feature): array
    {
        return match ($feature) {
            ModelTrainingJob::FEATURE_FORECAST => [
                'mape' => 0.082,
                'mae' => 0.96,
                'rmse' => 1.42,
            ],
            ModelTrainingJob::FEATURE_RISK => [
                'accuracy' => 0.91,
                'f1' => 0.86,
                'auc' => 0.93,
            ],
            ModelTrainingJob::FEATURE_RAG => [
                'documents_indexed' => 23820,
                'duration_seconds' => 640,
            ],
            ModelTrainingJob::FEATURE_ACTIONS => [
                'actions_refreshed' => 156,
                'latency_ms' => 420,
            ],
            ModelTrainingJob::FEATURE_WORKFLOWS => [
                'templates_updated' => 18,
                'latency_ms' => 520,
            ],
            ModelTrainingJob::FEATURE_CHAT => [
                'sessions_sampled' => 780,
            ],
            default => [],
        };
    }
}
