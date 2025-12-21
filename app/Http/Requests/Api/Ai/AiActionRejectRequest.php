<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiActionRejectRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'thread_id' => ['nullable', 'integer', 'min:1'],
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

    public function threadId(): ?int
    {
        $value = $this->validated()['thread_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
