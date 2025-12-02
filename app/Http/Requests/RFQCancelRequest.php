<?php

namespace App\Http\Requests;

class RFQCancelRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('cancelledAt') && ! $this->has('cancelled_at')) {
            $payload['cancelled_at'] = $this->input('cancelledAt');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'cancelled_at' => ['nullable', 'date'],
        ];
    }
}
