<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;

class ExtendRfqDeadlineRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'new_due_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'notify_suppliers' => ['nullable', 'boolean'],
        ];
    }
}
