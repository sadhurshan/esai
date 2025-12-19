<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiActionFeedbackRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rating(): int
    {
        return (int) $this->validated()['rating'];
    }

    public function comment(): ?string
    {
        $value = $this->validated()['comment'] ?? null;

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
