<?php

namespace App\Http\Requests\Admin;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;

class ToggleApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $apiKey = $this->route('key');

        return $apiKey instanceof ApiKey
            ? ($this->user()?->can('toggle', $apiKey) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
