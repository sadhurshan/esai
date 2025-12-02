<?php

namespace App\Http\Controllers\Api;

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Enums\DownloadJobStatus;
use App\Http\Requests\Downloads\StoreDownloadJobRequest;
use App\Http\Resources\DownloadJobResource;
use App\Models\DownloadJob;
use App\Services\DownloadJobService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DownloadJobController extends ApiController
{
    public function __construct(
        private readonly DownloadJobService $downloadJobService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $perPage = $this->perPage($request, 25, 100);
        $cursor = $request->query('cursor');

        $query = DownloadJob::query()
            ->forCompany($companyId)
            ->with(['requester:id,name,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, DownloadJobStatus::values(), true)) {
            $query->where('status', $status);
        }

        $format = $request->query('format');
        if (is_string($format) && in_array($format, DownloadFormat::values(), true)) {
            $query->where('format', $format);
        }

        $documentType = $request->query('document_type');
        if (is_string($documentType) && in_array($documentType, DownloadDocumentType::values(), true)) {
            $query->where('document_type', $documentType);
        }

        $paginator = $query
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, DownloadJobResource::class);

        return $this->ok([
            'items' => $items,
        ], 'Download jobs retrieved.', $meta);
    }

    public function store(StoreDownloadJobRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $user->company_id = $companyId;

        $validated = $request->validated();

        try {
            $job = $this->downloadJobService->request(
                $user,
                $validated['document_type'],
                (int) $validated['document_id'],
                $validated['format'],
                $validated['reference'] ?? null,
                $validated['meta'] ?? []
            );
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok((new DownloadJobResource($job))->toArray($request), 'Download queued.')->setStatusCode(201);
    }

    public function show(Request $request, DownloadJob $downloadJob): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        if (! $this->jobVisibleToCompany($companyId, $downloadJob)) {
            return $this->fail('Not found.', 404);
        }

        $downloadJob->loadMissing('requester');

        return $this->ok((new DownloadJobResource($downloadJob))->toArray($request), 'Download job retrieved.');
    }

    public function retry(Request $request, DownloadJob $downloadJob): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        if (! $this->jobVisibleToCompany($companyId, $downloadJob)) {
            return $this->fail('Not found.', 404);
        }

        $job = $this->downloadJobService->retry($downloadJob);

        return $this->ok((new DownloadJobResource($job))->toArray($request), 'Download job re-queued.');
    }

    public function download(Request $request, DownloadJob $downloadJob)
    {
        if (! $request->hasValidSignature()) {
            return $this->fail('Invalid download signature.', 403);
        }

        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if (! $this->jobVisibleToCompany($companyId, $downloadJob)) {
            return $this->fail('Not found.', 404);
        }

        if (! $downloadJob->isReady()) {
            return $this->fail('Download is not ready.', 409);
        }

        if ($downloadJob->storage_disk === null || $downloadJob->file_path === null) {
            return $this->fail('Download file unavailable. Please request a new export.', 410);
        }

        if ($downloadJob->expires_at !== null && $downloadJob->expires_at->isPast()) {
            return $this->fail('Download file has expired. Please request a new export.', 410);
        }

        $disk = Storage::disk($downloadJob->storage_disk);

        if (! $disk->exists($downloadJob->file_path)) {
            return $this->fail('Download file unavailable. Please request a new export.', 410);
        }

        $filename = $downloadJob->filename ?? basename($downloadJob->file_path);
        $format = $downloadJob->format instanceof DownloadFormat ? $downloadJob->format : DownloadFormat::from($downloadJob->format);
        $mime = $format === DownloadFormat::Csv ? 'text/csv' : 'application/pdf';

        $this->auditLogger->custom($downloadJob, 'download_job_downloaded', [
            'downloaded_by' => $user->id,
            'format' => $format->value,
        ]);

        if ($this->shouldUseTemporaryUrl($downloadJob->storage_disk)) {
            try {
                $temporaryUrl = $disk->temporaryUrl(
                    $downloadJob->file_path,
                    now()->addMinutes(15),
                    [
                        'ResponseContentDisposition' => $this->attachmentDisposition($filename),
                        'ResponseContentType' => $mime,
                    ]
                );

                return redirect()->away($temporaryUrl);
            } catch (Throwable) {
                // Fall back to streaming for drivers without temporary URL support.
            }
        }

        try {
            $stream = $disk->readStream($downloadJob->file_path);
        } catch (Throwable) {
            return $this->fail('Download file unavailable. Please request a new export.', 410);
        }

        if ($stream === false) {
            return $this->fail('Download file unavailable. Please request a new export.', 410);
        }

        return response()->streamDownload(function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false) {
                        break;
                    }

                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    private function jobVisibleToCompany(int $companyId, DownloadJob $downloadJob): bool
    {
        return (int) $downloadJob->company_id === (int) $companyId;
    }

    private function shouldUseTemporaryUrl(string $diskName): bool
    {
        $driver = config("filesystems.disks.{$diskName}.driver");

        if ($driver === null) {
            return false;
        }

        return $driver !== 'local';
    }

    private function attachmentDisposition(string $filename): string
    {
        $sanitized = Str::of($filename)
            ->replace('"', '')
            ->replace(["\r", "\n"], '')
            ->value();

        return sprintf('attachment; filename="%s"', $sanitized);
    }
}
