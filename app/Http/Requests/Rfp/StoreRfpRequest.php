<?php

namespace App\Http\Requests\Rfp;

use App\Enums\RfpStatus;
use App\Http\Requests\ApiFormRequest;

class StoreRfpRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'problem_objectives' => ['required', 'string'],
            'scope' => ['required', 'string'],
            // TODO: clarify with spec - capture structured milestone dates instead of free-form text for timeline
            'timeline' => ['required', 'string', 'max:1000'],
            'evaluation_criteria' => ['required', 'string'],
            'proposal_format' => ['required', 'string'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', RfpStatus::values())],
            'ai_assist_enabled' => ['sometimes', 'boolean'],
            'ai_suggestions' => ['nullable', 'array'],
            'ai_suggestions.*' => ['string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
