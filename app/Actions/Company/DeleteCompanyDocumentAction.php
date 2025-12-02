<?php

namespace App\Actions\Company;

use App\Models\CompanyDocument;
use App\Models\Document;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteCompanyDocumentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(CompanyDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->loadMissing('document');
            $before = $document->toArray();
            $linked = $document->document;
            $path = $linked?->path ?? $document->path;

            $document->delete();

            if ($linked instanceof Document) {
                $linked->delete();
            } elseif ($document->document_id !== null) {
                Document::query()->whereKey($document->document_id)->delete();
            }

            $this->deleteBinary($path);

            $this->auditLogger->deleted($document, $before);
        });
    }

    private function deleteBinary(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = (string) config('documents.disk', config('filesystems.default', 'local'));

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
