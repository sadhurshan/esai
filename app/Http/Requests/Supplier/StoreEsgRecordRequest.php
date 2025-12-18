<?php

namespace App\Http\Requests\Supplier;

use App\Enums\EsgCategory;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\InteractsWithDocumentRules;
use Illuminate\Validation\Rule;

class StoreEsgRecordRequest extends ApiFormRequest
{
    use InteractsWithDocumentRules;

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
        $extensions = $this->documentAllowedExtensions();
        $maxKilobytes = $this->documentMaxKilobytes();

        return [
            'category' => ['required', Rule::in(EsgCategory::values())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required_unless:category,emission', 'file', 'max:'.$maxKilobytes, 'mimes:'.implode(',', $extensions)],
            'data_json' => [Rule::requiredIf(fn () => $this->input('category') === EsgCategory::Emission->value), 'array'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'approved_at' => ['nullable', 'date'],
        ];
    }
}
