<?php

namespace App\Http\Resources;

use App\Models\QuoteRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuoteRevision */
class QuoteRevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getRouteKey(),
            'quote_id' => (string) $this->quote_id,
            'revision_no' => $this->revision_no,
            'data' => $this->data_json ?? [],
            'document' => $this->whenLoaded('document', fn () => (new DocumentResource($this->document))->toArray($request)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
