<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\ApiFormRequest;

class UpdateEsgRecordRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $dataJson = $this->input('data_json');

        if (is_string($dataJson)) {
            $decoded = json_decode($dataJson, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['data_json' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:today'],
            'approved_at' => ['sometimes', 'nullable', 'date'],
            'data_json' => ['sometimes', 'array'],
        ];
    }
}
