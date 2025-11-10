<?php

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessExportRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $exportRequestId)
    {
        $this->queue = 'exports';
    }

    public function handle(ExportService $exportService): void
    {
        $request = ExportRequest::query()->find($this->exportRequestId);

        if (! $request instanceof ExportRequest) {
            return;
        }

        $exportService->processRequest($request);
    }
}
