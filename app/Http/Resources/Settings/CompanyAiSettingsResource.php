<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyAiSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'llm_answers_enabled' => (bool) $this->llm_answers_enabled,
            'llm_provider' => $this->llm_provider ?? 'dummy',
        ];
    }
}
