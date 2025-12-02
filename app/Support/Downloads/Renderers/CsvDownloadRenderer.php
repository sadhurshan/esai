<?php

namespace App\Support\Downloads\Renderers;

use App\Support\Downloads\DocumentDownloadPayload;
use App\Support\Downloads\DownloadArtifact;

class CsvDownloadRenderer
{
    public function render(DocumentDownloadPayload $payload): DownloadArtifact
    {
        $handle = fopen('php://temp', 'w+');

        foreach ($payload->csvRows as $row) {
            fputcsv($handle, $row ?? []);
        }

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        $filename = sprintf('%s.csv', $payload->baseFilename);

        return new DownloadArtifact($filename, 'text/csv', $contents);
    }
}
