<?php

namespace App\Http\Resources;

use App\Models\AiChatMessage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiChatMessage
 */
class AiChatMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'content_text' => $this->content_text,
            'content' => is_array($this->content_json) ? $this->content_json : null,
            'citations' => is_array($this->citations_json) ? $this->citations_json : [],
            'tool_calls' => is_array($this->tool_calls_json) ? $this->tool_calls_json : [],
            'tool_results' => is_array($this->tool_results_json) ? $this->tool_results_json : [],
            'latency_ms' => $this->latency_ms,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
