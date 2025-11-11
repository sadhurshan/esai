<?php

namespace App\Http\Requests\Admin;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApiKey::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:191'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'max:191'],
            'active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
