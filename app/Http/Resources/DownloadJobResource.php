<?php

namespace App\Http\Resources;

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Enums\DownloadJobStatus;
use App\Models\DownloadJob;
use App\Services\DownloadJobService;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DownloadJob */
class DownloadJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $service = app(DownloadJobService::class);

        return [
            'id' => $this->getKey(),
            'document_type' => $this->document_type instanceof DownloadDocumentType ? $this->document_type->value : $this->document_type,
            'document_id' => $this->document_id,
            'reference' => $this->reference,
            'format' => $this->format instanceof DownloadFormat ? $this->format->value : $this->format,
            'status' => $this->status instanceof DownloadJobStatus ? $this->status->value : $this->status,
            'filename' => $this->filename,
            'attempts' => $this->attempts,
            'meta' => $this->meta ?? [],
            'error_message' => $this->error_message,
            'requested_at' => $this->created_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_attempted_at' => $this->last_attempted_at?->toIso8601String(),
            'requested_by' => $this->when(
                $this->relationLoaded('requester') || $this->requested_by !== null,
                fn () => [
                    'id' => $this->requester?->id,
                    'name' => $this->requester?->name,
                    'email' => $this->requester?->email,
                ],
            ),
            'download_url' => $service->generateSignedUrl($this->resource),
        ];
    }
}
