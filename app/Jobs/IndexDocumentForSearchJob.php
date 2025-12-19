<?php

namespace App\Jobs;

use App\Models\AiDocumentIndex;
use App\Models\Document;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Services\Documents\DocumentTextExtractor;
use App\Support\CompanyContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IndexDocumentForSearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $companyId,
        public int $documentId,
        public string $docVersion
    ) {
        $this->queue = 'ai-indexing';
    }

    public function handle(AiClient $client, AiEventRecorder $recorder): void
    {
        CompanyContext::set($this->companyId);

        $document = Document::query()
            ->where('company_id', $this->companyId)
            ->find($this->documentId);

        if (! $document instanceof Document) {
            Log::warning('index_document_missing', [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
            ]);

            return;
        }

        $indexRecord = AiDocumentIndex::query()->firstOrCreate(
            [
                'company_id' => $this->companyId,
                'doc_id' => (string) $document->getKey(),
                'doc_version' => $this->docVersion,
            ],
            [
                'source_type' => (string) ($document->kind ?? 'document'),
                'title' => (string) ($document->filename ?? 'Document '.$document->getKey()),
                'mime_type' => (string) ($document->mime ?? 'application/octet-stream'),
            ],
        );

        $startedAt = microtime(true);
        $text = '';

        try {
            $text = $this->resolveDocumentText($document);
            $payload = $this->buildPayload($document, $text);
            $response = $client->indexDocument($payload);

            if (($response['status'] ?? 'error') !== 'success') {
                throw new RuntimeException($response['message'] ?? 'Indexing failed');
            }

            $indexedChunks = (int) data_get($response, 'data.indexed_chunks', 0);
            $indexRecord->markSuccess($indexedChunks);

            $recorder->record(
                companyId: $this->companyId,
                userId: null,
                feature: 'index_document',
                requestPayload: $this->telemetryPayload($payload, $text),
                responsePayload: $response['data'] ?? null,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                status: 'success',
                errorMessage: null,
                entityType: Document::class,
                entityId: $document->getKey(),
            );

            Log::info('index_document_success', [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
                'doc_version' => $this->docVersion,
                'indexed_chunks' => $indexedChunks,
            ]);
        } catch (Throwable $exception) {
            $message = $exception->getMessage() ?: 'Document indexing failed';
            $indexRecord->markFailure($message);

            $recorder->record(
                companyId: $this->companyId,
                userId: null,
                feature: 'index_document',
                requestPayload: [
                    'company_id' => $this->companyId,
                    'document_id' => $this->documentId,
                    'doc_version' => $this->docVersion,
                    'text_bytes' => strlen($text),
                ],
                responsePayload: null,
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                status: 'error',
                errorMessage: $message,
                entityType: Document::class,
                entityId: $document->getKey(),
            );

            Log::error('index_document_failure', [
                'company_id' => $this->companyId,
                'document_id' => $this->documentId,
                'doc_version' => $this->docVersion,
                'error' => $message,
            ]);

            throw $exception;
        } finally {
            CompanyContext::clear();
        }
    }

    private function resolveDocumentText(Document $document): string
    {
        $meta = $document->meta ?? [];
        $metaText = trim((string) Arr::get($meta, 'text')); // TODO: confirm canonical meta key for stored text content

        if ($metaText !== '') {
            return $metaText;
        }

        $mime = strtolower((string) ($document->mime ?? ''));

        if (Str::startsWith($mime, 'text/')) {
            $contents = trim($this->readDocumentContents($document));

            if ($contents !== '') {
                return $contents;
            }
        }

        if (Str::contains($mime, 'pdf')) {
            $extractor = $this->resolveTextExtractor();

            if ($extractor === null) {
                throw new RuntimeException('PDF text extractor is not configured.');
            }

            $text = trim((string) ($extractor->extract($document) ?? ''));

            if ($text === '') {
                throw new RuntimeException('Unable to extract text from PDF document.');
            }

            return $text;
        }

        $contents = trim($this->readDocumentContents($document));

        if ($contents === '') {
            throw new RuntimeException('Document does not contain extractable text.');
        }

        return $contents;
    }

    private function readDocumentContents(Document $document): string
    {
        $path = (string) ($document->path ?? '');

        if ($path === '') {
            throw new RuntimeException('Document is missing a storage path.');
        }

        $disk = config('documents.disk', config('filesystems.default', 'public'));

        try {
            return (string) Storage::disk($disk)->get($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to read document contents: '.$exception->getMessage(), 0, $exception);
        }
    }

    private function resolveTextExtractor(): ?DocumentTextExtractor
    {
        try {
            return app()->bound(DocumentTextExtractor::class)
                ? app(DocumentTextExtractor::class)
                : null;
        } catch (BindingResolutionException $exception) {
            Log::warning('document_text_extractor_missing', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Document $document, string $text): array
    {
        return [
            'company_id' => $this->companyId,
            'doc_id' => (string) $document->getKey(),
            'doc_version' => $this->docVersion,
            'title' => (string) ($document->filename ?? 'Document '.$document->getKey()),
            'source_type' => (string) ($document->kind ?? 'document'),
            'mime_type' => (string) ($document->mime ?? 'application/octet-stream'),
            'text' => $text,
            'metadata' => [
                'documentable_type' => $document->documentable_type,
                'documentable_id' => $document->documentable_id,
                'category' => $document->category,
                'version_number' => $document->version_number,
                'visibility' => $document->visibility,
            ],
            'acl' => [], // TODO: clarify ACL scopes required for document search results
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function telemetryPayload(array $payload, string $text): array
    {
        return [
            'company_id' => $payload['company_id'],
            'doc_id' => $payload['doc_id'],
            'doc_version' => $payload['doc_version'],
            'source_type' => $payload['source_type'],
            'mime_type' => $payload['mime_type'],
            'text_bytes' => strlen($text),
            'text_preview' => Str::limit($text, 120, '...'),
        ];
    }
}
