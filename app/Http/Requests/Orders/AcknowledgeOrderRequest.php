<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcknowledgeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'decline'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function decision(): string
    {
        $choice = $this->validated()['decision'] ?? 'accept';

        return $choice === 'decline' ? 'declined' : 'acknowledged';
    }
}
