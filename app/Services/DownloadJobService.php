<?php

namespace App\Services;

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Enums\DownloadJobStatus;
use App\Jobs\ProcessDownloadJob;
use App\Models\CreditNote;
use App\Models\DownloadJob;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Downloads\DocumentDownloadPayload;
use App\Support\Downloads\DownloadArtifact;
use App\Support\Downloads\DownloadPayloadFactory;
use App\Support\Downloads\Renderers\CsvDownloadRenderer;
use App\Support\Downloads\Renderers\PdfDownloadRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class DownloadJobService
{
    private readonly string $disk;

    private readonly int $ttlDays;

    private readonly int $maxAttempts;

    public function __construct(
        private readonly DownloadPayloadFactory $payloadFactory,
        private readonly PdfDownloadRenderer $pdfRenderer,
        private readonly CsvDownloadRenderer $csvRenderer,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->disk = (string) config('downloads.disk', 'downloads');
        $this->ttlDays = max(1, (int) config('downloads.ttl_days', 7));
        $this->maxAttempts = max(1, (int) config('downloads.max_attempts', 3));
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function request(
        User $user,
        DownloadDocumentType|string $documentType,
        int $documentId,
        DownloadFormat|string $format,
        ?string $reference = null,
        array $meta = []
    ): DownloadJob {
        $type = $documentType instanceof DownloadDocumentType
            ? $documentType
            : DownloadDocumentType::from($documentType);
        $formatEnum = $format instanceof DownloadFormat
            ? $format
            : DownloadFormat::from($format);

        $companyId = $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User must belong to a company to request a download.'],
            ]);
        }

        $document = $this->loadDocument($type, $documentId);

        if ((int) $document->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'document' => ['Requested document is not part of your company.'],
            ]);
        }

        $reference ??= $this->resolveReference($type, $document);
        $payloadMeta = array_filter(array_merge([
            'requested_by_name' => $user->name,
            'requested_by_id' => $user->id,
            'document_title' => $document->title ?? null,
        ], $meta), static fn ($value) => $value !== null && $value !== '');

        /** @var DownloadJob $job */
        $job = DB::transaction(function () use ($companyId, $user, $type, $documentId, $formatEnum, $reference, $payloadMeta): DownloadJob {
            return DownloadJob::create([
                'company_id' => $companyId,
                'requested_by' => $user->id,
                'document_type' => $type,
                'document_id' => $documentId,
                'reference' => $reference,
                'format' => $formatEnum,
                'status' => DownloadJobStatus::Queued,
                'meta' => empty($payloadMeta) ? null : $payloadMeta,
            ]);
        });

        $this->auditLogger->created($job, [
            'document_type' => $type->value,
            'format' => $formatEnum->value,
            'reference' => $job->reference,
        ]);

        ProcessDownloadJob::dispatch($job->id);

        return $job->fresh(['requester']);
    }

    public function process(DownloadJob $job): void
    {
        if ($job->status === DownloadJobStatus::Ready && $job->isReady()) {
            return;
        }

        if ($job->attempts >= $this->maxAttempts && $job->status === DownloadJobStatus::Failed) {
            return;
        }

        $this->applyChanges($job, [
            'status' => DownloadJobStatus::Processing,
            'attempts' => $job->attempts + 1,
            'last_attempted_at' => now(),
            'error_message' => null,
        ]);

        try {
            $payload = $this->payloadFactory->build($job);
            $artifact = $this->renderArtifact($job, $payload);
            $relativePath = $this->storeArtifact($job, $artifact);

            $this->applyChanges($job, [
                'status' => DownloadJobStatus::Ready,
                'storage_disk' => $this->disk,
                'file_path' => $relativePath,
                'filename' => $artifact->filename,
                'ready_at' => now(),
                'expires_at' => now()->addDays($this->ttlDays),
            ]);
        } catch (\Throwable $exception) {
            $this->applyChanges($job, [
                'status' => DownloadJobStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            report($exception);

            throw $exception;
        }
    }

    public function retry(DownloadJob $job): DownloadJob
    {
        $this->deleteArtifact($job);

        $this->applyChanges($job, [
            'status' => DownloadJobStatus::Queued,
            'storage_disk' => null,
            'file_path' => null,
            'filename' => null,
            'ready_at' => null,
            'expires_at' => null,
            'error_message' => null,
            'attempts' => 0,
            'last_attempted_at' => null,
        ]);

        ProcessDownloadJob::dispatch($job->id);

        return $job->fresh(['requester']);
    }

    public function purgeExpired(): int
    {
        $disk = Storage::disk($this->disk);
        $purged = 0;

        DownloadJob::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->chunkById(100, function ($jobs) use ($disk, &$purged): void {
                foreach ($jobs as $job) {
                    if ($job->file_path !== null && $disk->exists($job->file_path)) {
                        $disk->delete($job->file_path);
                    }

                    $before = Arr::only($job->toArray(), ['file_path', 'storage_disk']);

                    $job->forceFill([
                        'file_path' => null,
                        'filename' => null,
                        'storage_disk' => null,
                    ])->save();

                    $this->auditLogger->updated($job, $before, Arr::only($job->toArray(), ['file_path', 'storage_disk']));

                    $purged++;
                }
            });

        return $purged;
    }

    public function generateSignedUrl(DownloadJob $job): ?string
    {
        if (! $job->isReady()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'downloads.file',
            now()->addMinutes(10),
            ['downloadJob' => $job->getKey()]
        );
    }

    private function renderArtifact(DownloadJob $job, DocumentDownloadPayload $payload): DownloadArtifact
    {
        return match ($job->format) {
            DownloadFormat::Pdf => $this->pdfRenderer->render($payload),
            DownloadFormat::Csv => $this->csvRenderer->render($payload),
        };
    }

    private function storeArtifact(DownloadJob $job, DownloadArtifact $artifact): string
    {
        $this->deleteArtifact($job);

        $disk = Storage::disk($this->disk);
        $relativePath = $this->buildPath($job, $artifact->filename);

        $disk->put($relativePath, $artifact->contents);

        return $relativePath;
    }

    private function buildPath(DownloadJob $job, string $filename): string
    {
        return sprintf('%d/%d/%s', $job->company_id, $job->id, basename($filename));
    }

    private function deleteArtifact(DownloadJob $job): void
    {
        if ($job->file_path === null || $job->storage_disk === null) {
            return;
        }

        $disk = Storage::disk($job->storage_disk);

        if ($disk->exists($job->file_path)) {
            $disk->delete($job->file_path);
        }
    }

    private function applyChanges(DownloadJob $job, array $changes): void
    {
        $before = Arr::only($job->toArray(), array_keys($changes));
        $job->forceFill($changes)->save();
        $after = Arr::only($job->toArray(), array_keys($changes));
        $this->auditLogger->updated($job, $before, $after);
    }

    private function resolveReference(DownloadDocumentType $type, Model $document): string
    {
        return match ($type) {
            DownloadDocumentType::Rfq => $document->number ?? sprintf('RFQ-%05d', $document->getKey()),
            DownloadDocumentType::Quote => sprintf('QUOTE-%05d', $document->getKey()),
            DownloadDocumentType::PurchaseOrder => $document->po_number ?? sprintf('PO-%05d', $document->getKey()),
            DownloadDocumentType::Invoice => $document->invoice_number ?? sprintf('INV-%05d', $document->getKey()),
            DownloadDocumentType::GoodsReceipt => $document->number ?? sprintf('GRN-%05d', $document->getKey()),
            DownloadDocumentType::CreditNote => $document->credit_number ?? sprintf('CR-%05d', $document->getKey()),
        };
    }

    private function loadDocument(DownloadDocumentType $type, int $documentId): Model
    {
        return match ($type) {
            DownloadDocumentType::Rfq => RFQ::query()->select(['id', 'company_id', 'number', 'title'])->findOrFail($documentId),
            DownloadDocumentType::Quote => Quote::query()->select(['id', 'company_id'])->findOrFail($documentId),
            DownloadDocumentType::PurchaseOrder => PurchaseOrder::query()->select(['id', 'company_id', 'po_number'])->findOrFail($documentId),
            DownloadDocumentType::Invoice => Invoice::query()->select(['id', 'company_id', 'invoice_number'])->findOrFail($documentId),
            DownloadDocumentType::GoodsReceipt => GoodsReceiptNote::query()->select(['id', 'company_id', 'number'])->findOrFail($documentId),
            DownloadDocumentType::CreditNote => CreditNote::query()->select(['id', 'company_id', 'credit_number'])->findOrFail($documentId),
        };
    }
}
