<?php

namespace App\Http\Requests\Approval;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ApproveRequestActionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
