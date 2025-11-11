<?php

namespace App\Http\Requests\Admin;

use App\Enums\RateLimitScope;
use App\Models\RateLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRateLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rateLimit = $this->route('rate_limit');

        return $rateLimit instanceof RateLimit
            ? ($this->user()?->can('update', $rateLimit) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'nullable', 'exists:companies,id'],
            'window_seconds' => ['sometimes', 'integer', 'min:1'],
            'max_requests' => ['sometimes', 'integer', 'min:1'],
            'scope' => ['sometimes', new Enum(RateLimitScope::class)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
