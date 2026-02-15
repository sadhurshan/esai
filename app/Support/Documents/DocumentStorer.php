<?php

namespace App\Support\Documents;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Jobs\ParseCadDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentStorer
{
    /**
     * @var list<string>
     */
    private const DEFAULT_ALLOWED_EXTENSIONS = [
        'step',
        'stp',
        'iges',
        'igs',
        'dwg',
        'dxf',
        'sldprt',
        'stl',
        '3mf',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
        'png',
        'jpg',
        'jpeg',
        'tif',
        'tiff',
    ];

    private readonly int $maxFileSizeBytes;

    /**
     * @var list<string>
     */
    private readonly array $allowedExtensions;

    private readonly string $defaultDisk;

    public function __construct(private readonly AuditLogger $auditLogger)
    {
        $maxSizeMb = (int) config('documents.max_size_mb', 50);
        $this->maxFileSizeBytes = max(1, $maxSizeMb) * 1024 * 1024;

        $extensions = config('documents.allowed_extensions', self::DEFAULT_ALLOWED_EXTENSIONS);
        $this->allowedExtensions = array_values(array_filter(array_map(
            static fn (mixed $extension): string => strtolower((string) $extension),
            is_array($extensions) ? $extensions : self::DEFAULT_ALLOWED_EXTENSIONS
        )));

        $this->defaultDisk = (string) config('documents.disk', config('filesystems.default', 'local'));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function store(
        User $user,
        UploadedFile $file,
        string $category,
        ?int $companyId,
        string $documentableType,
        int $documentableId,
        array $options = []
    ): Document {
        $this->assertCategory($category);
        $this->validateFile($file);

        $company = $companyId ?? $user->company_id;

        if ($company === null) {
            throw ValidationException::withMessages([
                'company_id' => ['Unable to determine company context for document storage.'],
            ]);
        }

        $kind = $this->resolveKind($options['kind'] ?? null);
        $visibility = $this->resolveVisibility($options['visibility'] ?? null);
        $expiresAt = $this->normalizeExpiresAt($options['expires_at'] ?? null);
        $meta = $this->normalizeMeta($options['meta'] ?? []);
        $watermark = $this->normalizeWatermark($options['watermark'] ?? []);
        $disk = $options['disk'] ?? $this->defaultDisk;

        return DB::transaction(function () use (
            $user,
            $file,
            $kind,
            $category,
            $company,
            $documentableType,
            $documentableId,
            $visibility,
            $expiresAt,
            $meta,
            $watermark,
            $disk
        ): Document {
            $nextVersion = $this->determineNextVersion($documentableType, $documentableId, $category);

            if ($nextVersion > 1) {
                $this->markSupersededVersions($documentableType, $documentableId, $category, $nextVersion);
            }

            $path = $this->storeFile($file, $company, $documentableType, $documentableId, $nextVersion, $disk);
            $hash = hash_file('sha256', $file->getRealPath());
            $finalMeta = array_merge(['uploaded_by' => $user->id], $meta);

            $document = Document::create([
                'company_id' => $company,
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'kind' => $kind,
                'category' => $category,
                'visibility' => $visibility,
                'version_number' => $nextVersion,
                'expires_at' => $expiresAt,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType() ?? $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?? 0,
                'hash' => $hash,
                'watermark' => $watermark,
                'meta' => $finalMeta,
            ]);

            $this->auditLogger->created($document, [
                'version_number' => $document->version_number,
                'category' => $document->category,
                'visibility' => $document->visibility,
            ]);

            $this->refreshAttachmentCount($documentableType, $documentableId);

            if ($this->isCadCandidate($file, $document)) {
                DB::afterCommit(function () use ($document): void {
                    ParseCadDocumentJob::dispatch(
                        companyId: (int) $document->company_id,
                        documentId: (int) $document->getKey(),
                        documentVersion: (int) $document->version_number,
                    );
                });
            }

            return $document;
        });
    }

    private function isCadCandidate(UploadedFile $file, Document $document): bool
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $cadExtensions = ['step', 'stp', 'iges', 'igs', 'dwg', 'dxf', 'sldprt', 'stl', '3mf'];

        if (in_array($extension, $cadExtensions, true)) {
            return true;
        }

        $mime = strtolower((string) ($document->mime ?? ''));

        return str_contains($mime, 'cad') || str_contains($mime, 'step');
    }

    private function assertCategory(string $category): void
    {
        if (! in_array($category, DocumentCategory::values(), true)) {
            throw ValidationException::withMessages([
                'category' => ['Invalid document category provided.'],
            ]);
        }
    }

    private function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if ($extension === '' || ! in_array($extension, $this->allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported document type.'],
            ]);
        }

        $size = $file->getSize() ?? 0;

        if ($size <= 0 || $size > $this->maxFileSizeBytes) {
            $maxMb = (int) round($this->maxFileSizeBytes / (1024 * 1024));

            throw ValidationException::withMessages([
                'file' => ["Document exceeds the maximum allowed size of {$maxMb} MB."],
            ]);
        }
    }

    private function resolveKind(?string $kind): string
    {
        if ($kind === null) {
            throw ValidationException::withMessages([
                'kind' => ['Document kind is required.'],
            ]);
        }

        $normalized = strtolower($kind);

        if (! in_array($normalized, DocumentKind::values(), true)) {
            throw ValidationException::withMessages([
                'kind' => ['Invalid document kind provided.'],
            ]);
        }

        return $normalized;
    }

    private function resolveVisibility(?string $visibility): string
    {
        $allowed = config('documents.allowed_visibilities', ['private', 'company', 'public']);
        $value = strtolower((string) $visibility);

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        $default = config('documents.default_visibility', 'company');

        return in_array($default, $allowed, true) ? $default : 'company';
    }

    private function normalizeExpiresAt(mixed $expiresAt): ?Carbon
    {
        if ($expiresAt === null || $expiresAt === '') {
            return null;
        }

        return $expiresAt instanceof Carbon ? $expiresAt : Carbon::parse($expiresAt);
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta(mixed $meta): array
    {
        if ($meta === null) {
            return [];
        }

        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function determineNextVersion(string $documentableType, int $documentableId, string $category): int
    {
        $latest = Document::query()
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->where('category', $category)
            ->latest('version_number')
            ->first();

        return ($latest?->version_number ?? 0) + 1;
    }

    private function markSupersededVersions(string $documentableType, int $documentableId, string $category, int $nextVersion): void
    {
        $previousDocuments = Document::query()
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->where('category', $category)
            ->where('version_number', '<', $nextVersion)
            ->get();

        foreach ($previousDocuments as $previous) {
            $before = $previous->getOriginal();
            $meta = Arr::wrap($previous->meta);

            $meta['status'] = 'superseded';
            $meta['superseded_at'] = now()->toIso8601String();
            $meta['superseded_by_version'] = $nextVersion;

            $previous->meta = $meta;
            $previous->save();

            $this->auditLogger->updated($previous, $before, $previous->toArray());
        }
    }

    private function storeFile(
        UploadedFile $file,
        int $companyId,
        string $documentableType,
        int $documentableId,
        int $version,
        string $disk
    ): string {
        $typeSegment = Str::kebab(class_basename($documentableType));
        $directory = sprintf('documents/%s/%s/%s/v%d', $companyId, $typeSegment, $documentableId, $version);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $sanitizedName = Str::slug($baseName);

        if ($sanitizedName === '') {
            $sanitizedName = Str::uuid()->toString();
        }

        $storedName = $sanitizedName.'-'.now()->format('YmdHis').'.'.$extension;

        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $storedName);

        return $storedPath ?? $directory.'/'.$storedName;
    }

    /**
     * @param mixed $watermark
     * @return array<string, mixed>
     */
    private function normalizeWatermark(mixed $watermark): array
    {
        if ($watermark === null) {
            return [];
        }

        if (is_array($watermark)) {
            return $watermark;
        }

        if (is_string($watermark)) {
            $decoded = json_decode($watermark, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function refreshAttachmentCount(string $documentableType, int $documentableId): void
    {
        if (! is_subclass_of($documentableType, Model::class)) {
            return;
        }

        /** @var Model $model */
        $model = new $documentableType();

        if (! Schema::hasColumn($model->getTable(), 'attachments_count')) {
            return;
        }

        $count = Document::query()
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->count();

        $documentableType::query()
            ->whereKey($documentableId)
            ->update(['attachments_count' => $count]);
    }
}
