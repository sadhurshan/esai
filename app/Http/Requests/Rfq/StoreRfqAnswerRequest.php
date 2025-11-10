<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;

class StoreRfqAnswerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ];
    }
}
