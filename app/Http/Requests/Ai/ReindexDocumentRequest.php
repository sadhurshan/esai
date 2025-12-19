<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\ApiFormRequest;

class ReindexDocumentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'doc_id' => ['required', 'integer', 'min:1'],
            'doc_version' => ['required', 'string', 'max:100'],
        ];
    }
}
