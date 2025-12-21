<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiActionApproveRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'thread_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function threadId(): ?int
    {
        $value = $this->validated()['thread_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
