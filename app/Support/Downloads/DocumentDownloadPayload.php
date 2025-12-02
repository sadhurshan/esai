<?php

namespace App\Support\Downloads;

use App\Models\DownloadJob;

class DocumentDownloadPayload
{
    /**
     * @param array<string, mixed> $document
     * @param array<int, list<string>> $csvRows
     */
    public function __construct(
        public readonly DownloadJob $job,
        public readonly array $document,
        public readonly array $csvRows,
        public readonly string $baseFilename,
    ) {}
}
