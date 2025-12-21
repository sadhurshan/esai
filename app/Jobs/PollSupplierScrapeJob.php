<?php

namespace App\Jobs;

use App\Enums\SupplierScrapeJobStatus;
use App\Models\SupplierScrapeJob;
use App\Services\Ai\SupplierScrapeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollSupplierScrapeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public SupplierScrapeJob $scrapeJob)
    {
    }

    public function handle(SupplierScrapeService $service): void
    {
        $jobModel = SupplierScrapeJob::query()->find($this->scrapeJob->id);

        if ($jobModel === null || $this->isTerminal($jobModel)) {
            return;
        }

        $this->markRunning($jobModel);

        try {
            $service->refreshScrapeStatus($jobModel);
        } catch (Throwable $exception) {
            $jobModel->update([
                'status' => SupplierScrapeJobStatus::Failed,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            report($exception);

            return;
        }

        $jobModel->refresh();

        if ($jobModel->status === SupplierScrapeJobStatus::Completed) {
            $jobModel->finished_at ??= now();
            $jobModel->save();

            Log::info('supplier_scrape_job_complete', [
                'job_id' => $jobModel->id,
                'result_count' => $jobModel->result_count,
            ]);

            return;
        }

        if ($jobModel->status === SupplierScrapeJobStatus::Failed) {
            $jobModel->finished_at ??= now();
            $jobModel->save();

            return;
        }

        if ($this->hasTimedOut($jobModel)) {
            $message = sprintf(
                'Supplier scrape timed out after %d seconds.',
                $this->maxDurationSeconds()
            );

            $jobModel->update([
                'status' => SupplierScrapeJobStatus::Failed,
                'error_message' => $message,
                'finished_at' => now(),
            ]);

            $service->recordTimeout($jobModel, $message);

            return;
        }

        self::dispatch($jobModel)->delay(now()->addSeconds($this->pollIntervalSeconds()));
    }

    private function markRunning(SupplierScrapeJob $job): void
    {
        $updates = [];

        if ($job->status !== SupplierScrapeJobStatus::Running) {
            $updates['status'] = SupplierScrapeJobStatus::Running;
        }

        if ($job->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($updates !== []) {
            $job->fill($updates);
            $job->save();
        }
    }

    private function isTerminal(SupplierScrapeJob $job): bool
    {
        return in_array($job->status, [
            SupplierScrapeJobStatus::Completed,
            SupplierScrapeJobStatus::Failed,
        ], true);
    }

    private function hasTimedOut(SupplierScrapeJob $job): bool
    {
        $startedAt = $job->started_at ?? $job->created_at;

        if (! $startedAt instanceof Carbon) {
            return false;
        }

        return $startedAt->diffInSeconds(now()) >= $this->maxDurationSeconds();
    }

    private function pollIntervalSeconds(): int
    {
        $value = (int) config('ai.scraper.poll_interval_seconds', 30);

        return max(5, $value);
    }

    private function maxDurationSeconds(): int
    {
        $value = (int) config('ai.scraper.max_duration_seconds', 900);

        return max($this->pollIntervalSeconds(), $value);
    }
}
