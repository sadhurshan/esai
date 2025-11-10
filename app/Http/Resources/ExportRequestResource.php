<?php

namespace App\Http\Resources;

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use App\Models\ExportRequest;
use App\Services\ExportService;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExportRequest */
class ExportRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $downloadUrl = app(ExportService::class)->generateSignedUrl($this->resource);

        return [
            'id' => $this->id,
            'type' => $this->type instanceof ExportRequestType ? $this->type->value : $this->type,
            'status' => $this->status instanceof ExportRequestStatus ? $this->status->value : $this->status,
            'filters' => $this->filters ?? [],
            'requested_by' => $this->when(
                $this->relationLoaded('requester') || $this->requested_by !== null,
                fn () => [
                    'id' => $this->requester?->id,
                    'name' => $this->requester?->name,
                    'email' => $this->requester?->email,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'download_url' => $downloadUrl,
            'error_message' => $this->error_message,
        ];
    }
}
