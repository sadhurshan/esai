<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\ApiFormRequest;

class CopilotQueryRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:2000'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:20'],
            'allow_general' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.source_type' => ['nullable', 'string', 'max:100'],
            'filters.doc_id' => ['nullable', 'string', 'max:100'],
            'filters.doc_version' => ['nullable', 'string', 'max:100'],
            'filters.tags' => ['nullable', 'array'],
            'filters.tags.*' => ['required', 'string', 'max:100'],
        ];
    }
}
