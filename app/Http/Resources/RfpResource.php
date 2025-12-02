<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Rfp */
class RfpResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $statusValue = is_object($status) && method_exists($status, 'value') ? $status->value : $status;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'status' => $statusValue,
            'problem_objectives' => $this->problem_objectives,
            'scope' => $this->scope,
            'timeline' => $this->timeline,
            'evaluation_criteria' => $this->evaluation_criteria,
            'proposal_format' => $this->proposal_format,
            'ai_assist_enabled' => (bool) $this->ai_assist_enabled,
            'ai_suggestions' => $this->ai_suggestions ?? [],
            'published_at' => optional($this->published_at)->toIso8601String(),
            'in_review_at' => optional($this->in_review_at)->toIso8601String(),
            'awarded_at' => optional($this->awarded_at)->toIso8601String(),
            'closed_at' => optional($this->closed_at)->toIso8601String(),
            'meta' => $this->meta ?? [],
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
