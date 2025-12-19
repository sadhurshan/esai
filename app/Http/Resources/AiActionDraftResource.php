<?php

namespace App\Http\Resources;

use App\Models\AiActionDraft;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiActionDraft
 */
class AiActionDraftResource extends JsonResource
{
    public function toArray($request): array
    {
        $output = is_array($this->output_json) ? $this->output_json : [];
        $citations = is_array($this->citations_json) ? $this->citations_json : ($output['citations'] ?? []);
        $citations = is_array($citations) ? $citations : [];
        $payload = isset($output['payload']) && is_array($output['payload']) ? $output['payload'] : [];
        $warnings = isset($output['warnings']) && is_array($output['warnings']) ? $output['warnings'] : [];

        return [
            'id' => $this->id,
            'action_type' => $this->action_type,
            'status' => $this->status,
            'summary' => $output['summary'] ?? null,
            'payload' => $payload,
            'citations' => $citations,
            'confidence' => $output['confidence'] ?? null,
            'needs_human_review' => $output['needs_human_review'] ?? true,
            'warnings' => $warnings,
            'output' => $output,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
