<?php

namespace App\Http\Requests\Rma;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ReviewRmaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string'],
        ];
    }
}
