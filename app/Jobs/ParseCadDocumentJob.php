<?php

namespace App\Jobs;

use App\Models\AiDocumentExtraction;
use App\Models\Document;
use App\Services\Documents\CadExtractionService;
use App\Support\CompanyContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParseCadDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $companyId,
        public int $documentId,
        public int $documentVersion,
    ) {
        $this->queue = 'ai-indexing';
    }

    public function handle(CadExtractionService $extractionService): void
    {
        CompanyContext::set($this->companyId);

        $document = Document::query()
            ->where('company_id', $this->companyId)
            ->find($this->documentId);

        if (! $document instanceof Document) {
            Log::warning('cad_extract_missing_document', [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
            ]);

            CompanyContext::clear();
            return;
        }

        $record = AiDocumentExtraction::query()->firstOrCreate(
            [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
                'document_version' => $this->documentVersion,
            ],
            [
                'source_type' => $document->kind,
                'filename' => $document->filename,
                'mime_type' => $document->mime,
                'status' => 'pending',
            ],
        );

        try {
            $result = $extractionService->extract($document);

            $record->markCompleted(
                $result['extracted'],
                $result['gdt'],
                $result['similar_parts'],
            );

            Log::info('cad_extract_success', [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
                'document_version' => $this->documentVersion,
            ]);
        } catch (Throwable $exception) {
            $message = $exception->getMessage() ?: 'CAD extraction failed.';
            $record->markFailure($message);

            Log::error('cad_extract_failure', [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
                'document_version' => $this->documentVersion,
                'error' => $message,
            ]);

            throw $exception;
        } finally {
            CompanyContext::clear();
        }
    }
}
