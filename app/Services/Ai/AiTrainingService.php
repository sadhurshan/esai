<?php

namespace App\Services\Ai;

use App\Jobs\RunModelTrainingJob;
use App\Models\AiEvent;
use App\Models\ModelTrainingJob;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiTrainingService
{
    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function startTraining(string $feature, array $parameters = []): ModelTrainingJob
    {
        $feature = $this->normalizeFeature($feature);
        $this->ensureTrainingEnabled();

        $companyId = $this->resolveCompanyId($parameters);
        $job = $this->createJobRecord($companyId, $feature, $parameters);

        $latency = null;
        $remotePayload = null;
        $remoteResponse = null;

        try {
            $startedAt = microtime(true);
            $remotePayload = $this->callTrainingEndpoint($feature, $parameters);
            $latency = $this->calculateLatency($startedAt);
            $remoteResponse = $remotePayload['response'] ?? null;

            $microserviceJobId = $remotePayload['job_id'] ?? null;
            if ($microserviceJobId !== null) {
                CompanyContext::forCompany($companyId, function () use ($job, $microserviceJobId): void {
                    $job->forceFill(['microservice_job_id' => $microserviceJobId])->save();
                });
            }

            RunModelTrainingJob::dispatch($job->fresh());

            $this->recordEvent(
                $companyId,
                sprintf('ai_training_%s_start', $feature),
                ['feature' => $feature, 'parameters' => $parameters],
                $remoteResponse,
                AiEvent::STATUS_SUCCESS,
                null,
                $latency,
                $job->id,
            );
        } catch (Throwable $exception) {
            CompanyContext::forCompany($companyId, function () use ($job, $exception): void {
                $job->forceFill([
                    'status' => ModelTrainingJob::STATUS_FAILED,
                    'error_message' => $exception->getMessage(),
                    'finished_at' => Carbon::now(),
                ])->save();
            });

            $this->recordEvent(
                $companyId,
                sprintf('ai_training_%s_start', $feature),
                ['feature' => $feature, 'parameters' => $parameters],
                $remoteResponse,
                AiEvent::STATUS_ERROR,
                $exception->getMessage(),
                $latency,
                $job->id,
            );

            throw $exception;
        }

        return $job->fresh();
    }

    public function refreshStatus(ModelTrainingJob $job): void
    {
        if ($job->microservice_job_id === null) {
            return;
        }

        $companyId = (int) $job->company_id;
        $latency = null;
        $remotePayload = null;

        try {
            $startedAt = microtime(true);
            $remotePayload = $this->client->trainingStatus($job->microservice_job_id);
            $latency = $this->calculateLatency($startedAt);
        } catch (Throwable $exception) {
            $this->recordEvent(
                $companyId,
                sprintf('ai_training_%s_refresh', $job->feature),
                ['job_id' => $job->microservice_job_id],
                null,
                AiEvent::STATUS_ERROR,
                $exception->getMessage(),
                $latency,
                $job->id,
            );

            throw $exception;
        }

        $remoteJob = $remotePayload['job'] ?? null;
        if (! is_array($remoteJob)) {
            return;
        }

        $updated = $this->applyRemoteSnapshot($job, $remoteJob);
        $status = strtolower((string) ($remoteJob['status'] ?? ''));
        $eventStatus = $status === ModelTrainingJob::STATUS_FAILED ? AiEvent::STATUS_ERROR : AiEvent::STATUS_SUCCESS;

        $this->recordEvent(
            $companyId,
            sprintf('ai_training_%s_refresh', $job->feature),
            ['job_id' => $job->microservice_job_id],
            $remoteJob,
            $eventStatus,
            $remoteJob['error_message'] ?? null,
            $latency,
            $updated->id,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function scheduleTraining(string $feature, Carbon $nextRunAt, array $parameters = []): ModelTrainingJob
    {
        $feature = $this->normalizeFeature($feature);
        $this->ensureTrainingEnabled();

        $companyId = $this->resolveCompanyId($parameters);
        $payload = $parameters;
        $payload['scheduled_for'] = $nextRunAt->toIso8601String();

        $job = $this->createJobRecord($companyId, $feature, $payload);

        RunModelTrainingJob::dispatch($job->fresh())->delay($nextRunAt);

        $this->recordEvent(
            $companyId,
            sprintf('ai_training_%s_schedule', $feature),
            ['feature' => $feature, 'run_at' => $payload['scheduled_for']],
            null,
            AiEvent::STATUS_SUCCESS,
            null,
            null,
            $job->id,
        );

        return $job->fresh();
    }

    /**
     * @param array<string, mixed> $remoteJob
     */
    public function applyRemoteSnapshot(ModelTrainingJob $job, array $remoteJob): ModelTrainingJob
    {
        $companyId = (int) $job->company_id;

        return CompanyContext::forCompany($companyId, function () use ($job, $remoteJob): ModelTrainingJob {
            $updates = $this->buildRemoteSyncPayload($job, $remoteJob);

            if ($updates === []) {
                return $job->refresh();
            }

            $job->forceFill($updates)->save();

            return $job->refresh();
        });
    }

    private function ensureTrainingEnabled(): void
    {
        if (! (bool) config('ai_training.training_enabled', true)) {
            throw new RuntimeException('AI training is currently disabled.');
        }
    }

    private function normalizeFeature(string $feature): string
    {
        $normalized = strtolower(trim($feature));

        if (! in_array($normalized, ModelTrainingJob::allowedFeatures(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported AI training feature "%s".', $feature));
        }

        if (ModelTrainingJob::clientMethodForFeature($normalized) === null) {
            throw new InvalidArgumentException(sprintf('AI training feature "%s" is not yet implemented.', $feature));
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function resolveCompanyId(array $parameters): int
    {
        $companyId = Arr::get($parameters, 'company_id');

        if ($companyId === null) {
            $companyId = CompanyContext::get();
        }

        if ($companyId === null) {
            throw new RuntimeException('Company context is required for AI training.');
        }

        return (int) $companyId;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createJobRecord(int $companyId, string $feature, array $parameters): ModelTrainingJob
    {
        return CompanyContext::forCompany($companyId, function () use ($companyId, $feature, $parameters): ModelTrainingJob {
            return ModelTrainingJob::query()->create([
                'company_id' => $companyId,
                'feature' => $feature,
                'status' => ModelTrainingJob::STATUS_PENDING,
                'parameters_json' => $parameters,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array{job_id:?string,job:array<string,mixed>|null,response:array<string,mixed>}
     */
    private function callTrainingEndpoint(string $feature, array $parameters): array
    {
        $method = ModelTrainingJob::clientMethodForFeature($feature);

        if ($method === null || ! method_exists($this->client, $method)) {
            throw new InvalidArgumentException(sprintf('AI training feature "%s" is not supported by the microservice.', $feature));
        }

        /** @var callable $callable */
        $callable = [$this->client, $method];

        return $callable($parameters);
    }

    private function calculateLatency(float $startedAt): int
    {
        return (int) max(0, round((microtime(true) - $startedAt) * 1000));
    }

    private function recordEvent(
        int $companyId,
        string $feature,
        array $requestPayload,
        ?array $responsePayload,
        string $status,
        ?string $errorMessage,
        ?int $latencyMs,
        ?int $entityId
    ): void {
        $this->recorder->record(
            companyId: $companyId,
            userId: Auth::id(),
            feature: $feature,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            latencyMs: $latencyMs,
            status: $status,
            errorMessage: $errorMessage,
            entityType: ModelTrainingJob::class,
            entityId: $entityId,
        );
    }

    /**
     * @param array<string, mixed> $remoteJob
     * @return array<string, mixed>
     */
    private function buildRemoteSyncPayload(ModelTrainingJob $job, array $remoteJob): array
    {
        $updates = [];
        $status = strtolower((string) ($remoteJob['status'] ?? ''));

        if (in_array($status, [
            ModelTrainingJob::STATUS_PENDING,
            ModelTrainingJob::STATUS_RUNNING,
            ModelTrainingJob::STATUS_COMPLETED,
            ModelTrainingJob::STATUS_FAILED,
        ], true)) {
            $updates['status'] = $status;
        }

        if (isset($remoteJob['result']) && is_array($remoteJob['result'])) {
            $updates['result_json'] = $remoteJob['result'];
        }

        if (array_key_exists('error_message', $remoteJob)) {
            $updates['error_message'] = $remoteJob['error_message'];
        }

        $startedAt = $this->parseTimestamp($remoteJob['started_at'] ?? null);
        if ($startedAt !== null) {
            $updates['started_at'] = $startedAt;
        }

        $finishedAt = $this->parseTimestamp($remoteJob['finished_at'] ?? null);
        if ($finishedAt !== null) {
            $updates['finished_at'] = $finishedAt;
        }

        if ($status === ModelTrainingJob::STATUS_COMPLETED) {
            $updates['status'] = ModelTrainingJob::STATUS_COMPLETED;
            $updates['error_message'] = null;
            $updates['finished_at'] = $updates['finished_at'] ?? Carbon::now();
        } elseif ($status === ModelTrainingJob::STATUS_FAILED) {
            $updates['status'] = ModelTrainingJob::STATUS_FAILED;
            $updates['finished_at'] = $updates['finished_at'] ?? Carbon::now();
        }

        return $updates;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
