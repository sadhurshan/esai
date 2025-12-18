<?php

namespace App\Http\Resources;

use App\Enums\RfqClarificationType;
use App\Models\Document;
use App\Models\RfqClarification;
use App\Support\CompanyContext;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RfqClarification */
class RfqClarificationResource extends JsonResource
{
    /**
     * @var array<int, Document|null>
     */
    private static array $documentCache = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'author' => $this->transformUser(),
            'user' => $this->transformUser(),
            'type' => $this->resolveType(),
            'body' => $this->message,
            'message' => $this->message,
            'version_increment' => (bool) $this->version_increment,
            'version_no' => $this->version_no,
            'attachments' => $this->transformAttachments($request),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function transformUser(): ?array
    {
        $user = $this->whenLoaded('user');

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
        ];
    }

    private function resolveType(): string
    {
        $type = $this->resource->type;

        if ($type instanceof RfqClarificationType) {
            return $type->value;
        }

        return (string) $type;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transformAttachments($request): array
    {
        $entries = $this->attachmentMetadata();

        if ($entries === []) {
            return [];
        }

        $documentIds = array_values(array_unique(array_map(
            static fn (array $entry): int => (int) $entry['document_id'],
            $entries
        )));

        $this->primeDocumentCache($documentIds);

        $attachments = [];

        foreach ($entries as $entry) {
            $documentId = (int) $entry['document_id'];
            $document = self::$documentCache[$documentId] ?? null;

            if (! $document instanceof Document) {
                continue;
            }

            $downloadUrl = $document->temporaryDownloadUrl((int) config('documents.download_ttl_minutes', 10));

            if ($downloadUrl === null) {
                $downloadUrl = route('rfqs.clarifications.attachments.download', [
                    'rfq' => $this->rfq_id,
                    'clarification' => $this->id,
                    'attachment' => $document->id,
                ]);
            }

            $attachments[] = [
                'id' => (string) $document->id,
                'document_id' => (string) $document->id,
                'filename' => $entry['filename'] ?? $document->filename,
                'mime' => $entry['mime'] ?? $document->mime,
                'size_bytes' => (int) ($entry['size_bytes'] ?? $document->size_bytes ?? 0),
                'download_url' => $downloadUrl,
                'url' => $downloadUrl,
                'uploaded_by' => $entry['uploaded_by'] ?? null,
                'uploaded_at' => $entry['uploaded_at'] ?? $document->created_at?->toIso8601String(),
            ];
        }

        return $attachments;
    }

    /**
     * @param list<int> $documentIds
     */
    private function primeDocumentCache(array $documentIds): void
    {
        $missing = array_values(array_diff($documentIds, array_keys(self::$documentCache)));

        if ($missing === []) {
            return;
        }

        CompanyContext::bypass(static function () use ($missing): void {
            Document::query()
                ->whereIn('id', $missing)
                ->get()
                ->each(function (Document $document): void {
                    self::$documentCache[$document->id] = $document;
                });
        });
    }
}
