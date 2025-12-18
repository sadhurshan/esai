<?php

namespace App\Http\Requests\Admin;

use App\Models\AiEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAiEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AiEvent::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feature' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', Rule::in([AiEvent::STATUS_SUCCESS, AiEvent::STATUS_ERROR])],
            'entity' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
