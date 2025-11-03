<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;

class ClarifyRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:question,answer,amendment'],
            'message' => ['required', 'string'],
            'attachment_id' => ['nullable', 'integer', 'exists:documents,id'],
        ];
    }
}
