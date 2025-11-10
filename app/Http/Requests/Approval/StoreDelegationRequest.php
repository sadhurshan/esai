<?php

namespace App\Http\Requests\Approval;

use App\Http\Requests\ApiFormRequest;

class StoreDelegationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approver_user_id' => ['required', 'integer', 'exists:users,id'],
            'delegate_user_id' => ['required', 'integer', 'different:approver_user_id', 'exists:users,id'],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
