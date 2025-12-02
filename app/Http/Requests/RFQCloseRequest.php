<?php

namespace App\Http\Requests;

class RFQCloseRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('closedAt') && ! $this->has('closed_at')) {
            $payload['closed_at'] = $this->input('closedAt');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'closed_at' => ['nullable', 'date'],
        ];
    }
}
