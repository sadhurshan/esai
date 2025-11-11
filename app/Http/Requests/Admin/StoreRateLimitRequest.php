<?php

namespace App\Http\Requests\Admin;

use App\Enums\RateLimitScope;
use App\Models\RateLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRateLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RateLimit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'window_seconds' => ['required', 'integer', 'min:1'],
            'max_requests' => ['required', 'integer', 'min:1'],
            'scope' => ['required', new Enum(RateLimitScope::class)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
