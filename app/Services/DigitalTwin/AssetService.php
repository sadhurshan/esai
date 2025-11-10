<?php

namespace App\Services\DigitalTwin;

use App\Models\Asset;
use App\Models\Document;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AssetService
{
    /**
     * @var list<string>
     */
    private const VALID_STATUSES = ['active', 'standby', 'retired', 'maintenance'];

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(User $user, array $payload): Asset
    {
        $documentPayloads = $this->normalizeDocuments($payload['documents'] ?? []);
        $attributes = $this->extractAssetAttributes($payload);

        /** @var Asset $asset */
        $asset = $this->database->transaction(function () use ($attributes, $documentPayloads, $user): Asset {
            $asset = Asset::create($attributes);
            $this->auditLogger->created($asset, Arr::only($asset->getAttributes(), array_keys($attributes)));

            if ($documentPayloads !== []) {
                $this->storeDocuments($asset, $user, $documentPayloads);
            }

            return $asset->loadMissing(['location', 'system', 'documents']);
        });

        return $asset;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(Asset $asset, User $user, array $payload): Asset
    {
        $documentPayloads = $this->normalizeDocuments($payload['documents'] ?? []);
        $attributes = $this->extractAssetAttributes($payload, $asset);

        $this->database->transaction(function () use ($asset, $attributes, $documentPayloads, $user): void {
            if ($attributes !== []) {
                $before = Arr::only($asset->getOriginal(), array_keys($attributes));
                $asset->fill($attributes);
                $asset->save();
                $this->auditLogger->updated($asset, $before, Arr::only($asset->getAttributes(), array_keys($attributes)));
            }

            if ($documentPayloads !== []) {
                $this->storeDocuments($asset, $user, $documentPayloads);
            }
        });

        return $asset->refresh()->loadMissing(['location', 'system', 'documents']);
    }

    public function setStatus(Asset $asset, string $status): Asset
    {
        $status = strtolower($status);

        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid asset status.'],
            ]);
        }

        if ($asset->status === $status) {
            return $asset;
        }

        $before = ['status' => $asset->status];
        $asset->status = $status;
        $asset->save();

        $this->auditLogger->updated($asset, $before, ['status' => $status]);

        return $asset->fresh();
    }

    /**
     * @param array<int, array<string, mixed>|UploadedFile> $documents
     * @return list<array{file:UploadedFile, category:string, kind:string, visibility?:string|null, meta?:array|null}>
     */
    private function normalizeDocuments(array $documents): array
    {
        $normalized = [];

        foreach ($documents as $document) {
            if ($document instanceof UploadedFile) {
                $normalized[] = [
                    'file' => $document,
                    'category' => 'technical_manual',
                    'kind' => 'oem',
                    'visibility' => null,
                    'meta' => null,
                ];

                continue;
            }

            if (! is_array($document)) {
                continue;
            }

            $file = $document['file'] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $normalized[] = [
                'file' => $file,
                'category' => (string) ($document['category'] ?? 'technical_manual'),
                'kind' => (string) ($document['kind'] ?? 'oem'),
                'visibility' => $document['visibility'] ?? null,
                'meta' => $document['meta'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractAssetAttributes(array $payload, ?Asset $asset = null): array
    {
        $fields = [
            'company_id',
            'system_id',
            'location_id',
            'name',
            'tag',
            'serial_no',
            'model_no',
            'manufacturer',
            'commissioned_at',
            'status',
            'meta',
        ];

        $attributes = Arr::only($payload, $fields);

        if (array_key_exists('meta', $attributes)) {
            $attributes['meta'] = $this->normalizeMeta($attributes['meta'], $asset);
        }

        if (array_key_exists('status', $attributes)) {
            $status = strtolower((string) $attributes['status']);
            if (! in_array($status, self::VALID_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Invalid asset status.'],
                ]);
            }

            $attributes['status'] = $status;
        }

        return array_filter(
            $attributes,
            static fn ($value) => $value !== null
        );
    }

    /**
     * @param array<int, array{file:UploadedFile, category:string, kind:string, visibility?:string|null, meta?:array|null}> $documents
     */
    private function storeDocuments(Asset $asset, User $user, array $documents): void
    {
        foreach ($documents as $document) {
            $stored = $this->documentStorer->store(
                $user,
                $document['file'],
                $document['category'],
                $asset->company_id,
                $asset->getMorphClass(),
                (int) $asset->getKey(),
                [
                    'kind' => $document['kind'],
                    'visibility' => $document['visibility'] ?? null,
                    'meta' => $document['meta'] ?? [],
                ]
            );

            $asset->documents()->syncWithoutDetaching([$stored->id]);
        }
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta(mixed $meta, ?Asset $asset = null): array
    {
        if ($meta === null) {
            return $asset?->meta ?? [];
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
}
