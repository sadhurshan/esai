<?php

namespace App\Http\Requests\Rfp;

use App\Enums\RfpStatus;
use App\Http\Requests\ApiFormRequest;

class UpdateRfpRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'problem_objectives' => ['sometimes', 'string'],
            'scope' => ['sometimes', 'string'],
            'timeline' => ['sometimes', 'string', 'max:1000'],
            'evaluation_criteria' => ['sometimes', 'string'],
            'proposal_format' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', RfpStatus::values())],
            'ai_assist_enabled' => ['sometimes', 'boolean'],
            'ai_suggestions' => ['nullable', 'array'],
            'ai_suggestions.*' => ['string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
