<?php

namespace App\Console\Commands;

use App\Services\ExportService;
use Illuminate\Console\Command;

class CleanupExpiredExportsCommand extends Command
{
    protected $signature = 'exports:cleanup';

    protected $description = 'Remove expired export archives and clear file references.';

    public function __construct(private readonly ExportService $exportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $removed = $this->exportService->purgeExpiredExports();

        if ($removed > 0) {
            $this->info(sprintf('Cleaned %d expired export%s.', $removed, $removed === 1 ? '' : 's'));
        } else {
            $this->info('No expired exports to clean.');
        }

        return self::SUCCESS;
    }
}
