<?php

namespace App\Http\Resources;

use App\Models\AiChatThread;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiChatThread
 */
class AiChatThreadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'last_message_at' => optional($this->last_message_at)->toIso8601String(),
            'metadata' => is_array($this->metadata_json) ? $this->metadata_json : [],
            'thread_summary' => $this->thread_summary,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'messages' => $this->when($this->relationLoaded('messages'), function () use ($request) {
                return AiChatMessageResource::collection($this->messages)->toArray($request);
            }),
        ];
    }
}
