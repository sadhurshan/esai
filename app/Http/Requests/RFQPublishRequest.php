<?php

namespace App\Http\Requests;

class RFQPublishRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('notify_suppliers')) {
            $this->merge([
                'notify_suppliers' => filter_var(
                    $this->input('notify_suppliers'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'due_at' => ['required', 'date', 'after:now'],
            'publish_at' => ['nullable', 'date', 'before_or_equal:due_at'],
            'notify_suppliers' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
