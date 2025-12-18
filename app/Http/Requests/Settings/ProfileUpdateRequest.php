<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableString('job_title');
        $this->normalizeNullableString('phone');
        $this->normalizeNullableString('locale');
        $this->normalizeNullableString('timezone');
        $this->normalizeNullableString('avatar_path');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'timezone'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', File::image()->max(4096)],
        ];
    }

    private function normalizeNullableString(string $key): void
    {
        if (! $this->exists($key)) {
            return;
        }

        $value = $this->input($key);

        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            return;
        }

        $trimmed = trim($value);

        $this->merge([
            $key => $trimmed === '' ? null : $trimmed,
        ]);
    }
}
