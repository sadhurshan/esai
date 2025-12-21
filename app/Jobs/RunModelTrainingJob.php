<?php

namespace App\Jobs;

use App\Models\AiEvent;
use App\Models\ModelTrainingJob;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Services\Ai\AiTrainingService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class RunModelTrainingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ModelTrainingJob $trainingJob)
    {
    }

    public function handle(
        AiClient $client,
        AiEventRecorder $recorder,
        AiTrainingService $trainingService
    ): void {
        $job = $this->trainingJob->fresh();

        if (! $job instanceof ModelTrainingJob) {
            return;
        }

        CompanyContext::forCompany((int) $job->company_id, function () use ($job, $client, $recorder, $trainingService): void {
            if ($this->isTerminal($job)) {
                return;
            }

            $remoteDispatch = null;
            $latency = null;

            try {
                if ($job->microservice_job_id === null) {
                    $startedAt = microtime(true);
                    $remoteDispatch = $this->invokeTrainingEndpoint($job, $client);
                    $latency = $this->calculateLatency($startedAt);

                    $job->forceFill([
                        'microservice_job_id' => $remoteDispatch['job_id'],
                        'status' => ModelTrainingJob::STATUS_RUNNING,
                        'started_at' => Carbon::now(),
                    ])->save();

                    $this->recordJobEvent(
                        $job,
                        sprintf('ai_training_%s_dispatch', $job->feature),
                        ['parameters' => $job->parameters_json],
                        $remoteDispatch['response'] ?? null,
                        AiEvent::STATUS_SUCCESS,
                        $recorder,
                        $latency,
                    );
                } else {
                    $job->forceFill([
                        'status' => ModelTrainingJob::STATUS_RUNNING,
                        'started_at' => $job->started_at ?? Carbon::now(),
                    ])->save();
                }

                $this->pollUntilTerminal($job->fresh(), $client, $trainingService, $recorder);
            } catch (Throwable $exception) {
                $job->forceFill([
                    'status' => ModelTrainingJob::STATUS_FAILED,
                    'error_message' => $exception->getMessage(),
                    'finished_at' => Carbon::now(),
                ])->save();

                $this->recordJobEvent(
                    $job,
                    sprintf('ai_training_%s_run', $job->feature),
                    ['job_id' => $job->microservice_job_id],
                    $remoteDispatch['response'] ?? null,
                    AiEvent::STATUS_ERROR,
                    $recorder,
                    $latency,
                    $exception->getMessage(),
                );

                throw $exception;
            }
        });
    }

    private function pollUntilTerminal(
        ModelTrainingJob $job,
        AiClient $client,
        AiTrainingService $trainingService,
        AiEventRecorder $recorder
    ): void {
        $jobId = $job->microservice_job_id;

        if ($jobId === null) {
            throw new RuntimeException('Training job missing microservice identifier.');
        }

        $timeout = max(60, (int) config('ai_training.max_training_runtime_minutes', 30) * 60);
        $interval = max(5, (int) config('ai_training.status_poll_interval_seconds', 10));
        $deadline = microtime(true) + $timeout;

        while (microtime(true) <= $deadline) {
            $response = $client->trainingStatus($jobId);
            $remoteJob = $response['job'] ?? null;

            if (is_array($remoteJob)) {
                $job = $trainingService->applyRemoteSnapshot($job->fresh(), $remoteJob);

                if ($this->isTerminal($job)) {
                    $status = $job->status === ModelTrainingJob::STATUS_FAILED
                        ? AiEvent::STATUS_ERROR
                        : AiEvent::STATUS_SUCCESS;

                    $this->recordJobEvent(
                        $job,
                        sprintf('ai_training_%s_run', $job->feature),
                        ['job_id' => $jobId],
                        $remoteJob,
                        $status,
                        $recorder,
                        null,
                        $remoteJob['error_message'] ?? null,
                    );

                    return;
                }
            }

            sleep($interval);
        }

        $job->forceFill([
            'status' => ModelTrainingJob::STATUS_FAILED,
            'error_message' => 'Training status polling timed out.',
            'finished_at' => Carbon::now(),
        ])->save();

        $this->recordJobEvent(
            $job,
            sprintf('ai_training_%s_run', $job->feature),
            ['job_id' => $jobId],
            null,
            AiEvent::STATUS_ERROR,
            $recorder,
            null,
            'Training status polling timed out.',
        );
    }

    private function invokeTrainingEndpoint(ModelTrainingJob $job, AiClient $client): array
    {
        $method = ModelTrainingJob::clientMethodForFeature($job->feature);

        if ($method === null || ! method_exists($client, $method)) {
            throw new RuntimeException(sprintf('Training feature "%s" is not supported.', $job->feature));
        }

        /** @var callable $callable */
        $callable = [$client, $method];

        return $callable($job->parameters_json ?? []);
    }

    private function isTerminal(ModelTrainingJob $job): bool
    {
        return in_array($job->status, [
            ModelTrainingJob::STATUS_COMPLETED,
            ModelTrainingJob::STATUS_FAILED,
        ], true);
    }

    private function recordJobEvent(
        ModelTrainingJob $job,
        string $feature,
        array $requestPayload,
        ?array $responsePayload,
        string $status,
        AiEventRecorder $recorder,
        ?int $latency = null,
        ?string $errorMessage = null
    ): void {
        $recorder->record(
            companyId: (int) $job->company_id,
            userId: null,
            feature: $feature,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            latencyMs: $latency,
            status: $status,
            errorMessage: $errorMessage,
            entityType: ModelTrainingJob::class,
            entityId: $job->id,
        );
    }

    private function calculateLatency(float $startedAt): int
    {
        return (int) max(0, round((microtime(true) - $startedAt) * 1000));
    }
}
