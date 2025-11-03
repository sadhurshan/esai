<?php

namespace App\Http\Requests;

class AwardQuoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
        ];
    }
}
