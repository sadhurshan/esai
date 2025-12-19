<?php

namespace App\Http\Resources;

use App\Models\AiActionFeedback;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiActionFeedback
 */
class AiActionFeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ai_action_draft_id' => $this->ai_action_draft_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'user_id' => $this->user_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
