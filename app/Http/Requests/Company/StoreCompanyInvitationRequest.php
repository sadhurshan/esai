<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->includesSupplierRoles()) {
                return;
            }

            $user = $this->user();

            if ($user !== null) {
                $user->loadMissing('company');
            }

            $company = $user?->company;

            if (! $company || ! $company->isSupplierApproved()) {
                $validator->errors()->add('invitations', 'Supplier-role invitations require supplier approval.');
            }
        });
    }

    private function includesSupplierRoles(): bool
    {
        $invitations = $this->input('invitations', []);

        if (! is_array($invitations)) {
            return false;
        }

        foreach ($invitations as $invitation) {
            $role = $invitation['role'] ?? null;

            if (is_string($role) && in_array($role, ['supplier_admin', 'supplier_estimator'], true)) {
                return true;
            }
        }

        return false;
    }
}
