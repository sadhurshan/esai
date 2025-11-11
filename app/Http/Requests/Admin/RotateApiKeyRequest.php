<?php

namespace App\Http\Requests\Admin;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;

class RotateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $apiKey = $this->route('key');

        return $apiKey instanceof ApiKey
            ? ($this->user()?->can('rotate', $apiKey) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
