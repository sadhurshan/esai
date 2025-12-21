<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiChatCreateThreadRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function title(): ?string
    {
        $value = $this->validated()['title'] ?? null;

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
