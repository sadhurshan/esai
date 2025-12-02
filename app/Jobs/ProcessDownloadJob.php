<?php

namespace App\Jobs;

use App\Models\DownloadJob;
use App\Services\DownloadJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDownloadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $downloadJobId)
    {
        $this->queue = 'downloads';
    }

    public function handle(DownloadJobService $downloadJobService): void
    {
        $job = DownloadJob::query()->find($this->downloadJobId);

        if (! $job instanceof DownloadJob) {
            return;
        }

        $downloadJobService->process($job);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('downloads.job_failed', [
            'download_job_id' => $this->downloadJobId,
            'error' => $exception->getMessage(),
        ]);
    }
}
