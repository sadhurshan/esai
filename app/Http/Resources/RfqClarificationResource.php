<?php

namespace App\Http\Resources;

use App\Enums\RfqClarificationType;
use App\Models\Document;
use App\Models\RfqClarification;
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
            'user' => $this->transformUser(),
            'type' => $this->resolveType(),
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
        $ids = $this->attachmentIds();

        if ($ids === []) {
            return [];
        }

        $attachments = [];

        foreach ($ids as $id) {
            if (! array_key_exists($id, self::$documentCache)) {
                self::$documentCache[$id] = Document::query()->find($id);
            }

            $document = self::$documentCache[$id];

            if ($document instanceof Document) {
                $attachments[] = (new DocumentResource($document))->toArray($request);
            }
        }

        return $attachments;
    }
}
