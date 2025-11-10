<?php

namespace App\Console\Commands;

use App\Models\ExportRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredExportsCommand extends Command
{
    protected $signature = 'exports:cleanup';

    protected $description = 'Remove expired export archives and clear file references.';

    public function handle(): int
    {
        $disk = Storage::disk('exports');

        ExportRequest::query()
            ->whereNotNull('file_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->chunkById(100, function ($exports) use ($disk): void {
                foreach ($exports as $export) {
                    if ($disk->exists($export->file_path)) {
                        $disk->delete($export->file_path);
                    }

                    $export->forceFill([
                        'file_path' => null,
                    ])->save();

                    $this->info(sprintf('Cleaned export %d', $export->id));
                }
            });

        return self::SUCCESS;
    }
}
