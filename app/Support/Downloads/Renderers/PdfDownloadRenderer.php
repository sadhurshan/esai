<?php

namespace App\Support\Downloads\Renderers;

use App\Support\Downloads\DocumentDownloadPayload;
use App\Support\Downloads\DownloadArtifact;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class PdfDownloadRenderer
{
    public function render(DocumentDownloadPayload $payload): DownloadArtifact
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $html = View::make('pdf.downloads.document', [
            'document' => $payload->document,
        ])->render();

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $filename = sprintf('%s.pdf', $payload->baseFilename);

        return new DownloadArtifact($filename, 'application/pdf', $dompdf->output());
    }
}
