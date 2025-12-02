<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyInvitationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $roles = [
            'owner',
            'buyer_admin',
            'buyer_member',
            'buyer_requester',
            'supplier_admin',
            'supplier_estimator',
            'finance',
        ];

        return [
            'invitations' => ['required', 'array', 'min:1', 'max:25'],
            'invitations.*.email' => ['required', 'email:filter', 'max:255'],
            'invitations.*.role' => ['required', Rule::in($roles)],
            'invitations.*.expires_at' => ['nullable', 'date', 'after:now'],
            'invitations.*.message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return in_array($user->role, ['owner', 'buyer_admin'], true);
    }
}
