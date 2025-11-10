<?php

namespace App\Http\Requests\CreditNote;

use App\Http\Requests\ApiFormRequest;

class ReviewCreditNoteRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
