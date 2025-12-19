<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiActionRejectRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function reason(): string
    {
        return (string) $this->validated()['reason'];
    }
}
