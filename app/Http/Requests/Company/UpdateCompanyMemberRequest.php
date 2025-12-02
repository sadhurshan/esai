<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyMemberRequest extends ApiFormRequest
{
    private const ROLES = [
        'owner',
        'buyer_admin',
        'buyer_member',
        'buyer_requester',
        'supplier_admin',
        'supplier_estimator',
        'finance',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(self::ROLES)],
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
