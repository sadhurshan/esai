<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class BillingPortalSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (in_array($user->role, ['platform_super', 'platform_support'], true)) {
            return true;
        }

        return in_array($user->role, ['owner', 'buyer_admin'], true) && $user->company_id !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
