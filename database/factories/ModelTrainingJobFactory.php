<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ModelTrainingJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModelTrainingJob>
 */
class ModelTrainingJobFactory extends Factory
{
    protected $model = ModelTrainingJob::class;

    public function definition(): array
    {
        $feature = $this->faker->randomElement(ModelTrainingJob::FEATURES);
        $status = $this->faker->randomElement([
            ModelTrainingJob::STATUS_COMPLETED,
            ModelTrainingJob::STATUS_COMPLETED,
            ModelTrainingJob::STATUS_RUNNING,
            ModelTrainingJob::STATUS_PENDING,
            ModelTrainingJob::STATUS_FAILED,
        ]);

        $createdAt = Carbon::now()
            ->subDays($this->faker->numberBetween(0, 14))
            ->subMinutes($this->faker->numberBetween(0, 240));
        $startedAt = null;
        $finishedAt = null;
        $result = null;
        $error = null;

        if ($status !== ModelTrainingJob::STATUS_PENDING) {
            $startedAt = (clone $createdAt)->addMinutes($this->faker->numberBetween(1, 10));
        }

        if ($status === ModelTrainingJob::STATUS_COMPLETED) {
            $finishedAt = (clone $startedAt)->addMinutes($this->faker->numberBetween(4, 40));
            $result = $this->resultForFeature($feature);
        }

        if ($status === ModelTrainingJob::STATUS_FAILED) {
            $finishedAt = (clone $startedAt)->addMinutes($this->faker->numberBetween(2, 20));
            $error = $this->faker->randomElement([
                'Training data validation failed.',
                'Remote trainer timed out.',
                'Artifact upload rejected by policy.',
            ]);
        }

        $updatedAt = $finishedAt ?? $startedAt ?? $createdAt;

        return [
            'company_id' => Company::factory(),
            'feature' => $feature,
            'status' => $status,
            'microservice_job_id' => $this->faker->boolean(85) ? 'job_' . Str::lower(Str::random(10)) : null,
            'parameters_json' => $this->parametersForFeature($feature),
            'result_json' => $result,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'error_message' => $error,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parametersForFeature(string $feature): array
    {
        return match ($feature) {
            ModelTrainingJob::FEATURE_FORECAST => [
                'horizon' => $this->faker->numberBetween(7, 45),
                'window_days' => $this->faker->numberBetween(30, 120),
            ],
            ModelTrainingJob::FEATURE_RISK => [
                'risk_floor' => $this->faker->randomFloat(2, 0.05, 0.25),
                'review_samples' => $this->faker->numberBetween(250, 900),
            ],
            ModelTrainingJob::FEATURE_RAG => [
                'documents' => $this->faker->numberBetween(1200, 9200),
                'reindex_all' => $this->faker->boolean(35),
            ],
            ModelTrainingJob::FEATURE_ACTIONS => [
                'playbooks' => $this->faker->numberBetween(18, 64),
                'heuristics' => $this->faker->numberBetween(30, 90),
            ],
            ModelTrainingJob::FEATURE_WORKFLOWS => [
                'templates' => $this->faker->numberBetween(6, 24),
                'guardrails' => $this->faker->numberBetween(3, 12),
            ],
            ModelTrainingJob::FEATURE_CHAT => [
                'samples' => $this->faker->numberBetween(120, 600),
                'redactions' => $this->faker->numberBetween(4, 22),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resultForFeature(string $feature): array
    {
        return match ($feature) {
            ModelTrainingJob::FEATURE_FORECAST => [
                'mape' => $this->faker->randomFloat(3, 0.02, 0.14),
                'mae' => $this->faker->randomFloat(3, 0.4, 1.8),
                'rmse' => $this->faker->randomFloat(3, 0.7, 2.3),
            ],
            ModelTrainingJob::FEATURE_RISK => [
                'accuracy' => $this->faker->randomFloat(3, 0.74, 0.96),
                'f1' => $this->faker->randomFloat(3, 0.62, 0.91),
                'auc' => $this->faker->randomFloat(3, 0.7, 0.98),
            ],
            ModelTrainingJob::FEATURE_RAG => [
                'documents_indexed' => $this->faker->numberBetween(8000, 46000),
                'duration_seconds' => $this->faker->numberBetween(210, 980),
            ],
            ModelTrainingJob::FEATURE_ACTIONS => [
                'actions_refreshed' => $this->faker->numberBetween(40, 210),
                'latency_ms' => $this->faker->numberBetween(180, 980),
            ],
            ModelTrainingJob::FEATURE_WORKFLOWS => [
                'templates_updated' => $this->faker->numberBetween(6, 32),
                'latency_ms' => $this->faker->numberBetween(220, 1100),
            ],
            ModelTrainingJob::FEATURE_CHAT => [
                'sessions_sampled' => $this->faker->numberBetween(120, 1200),
            ],
            default => [],
        };
    }
}
