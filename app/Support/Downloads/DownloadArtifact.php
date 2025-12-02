<?php

namespace App\Support\Downloads;

class DownloadArtifact
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mime,
        public readonly string $contents,
    ) {}
}
