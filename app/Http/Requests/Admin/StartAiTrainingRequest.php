<?php

namespace App\Http\Requests\Admin;

use App\Models\ModelTrainingJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAiTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ModelTrainingJob::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feature' => ['required', 'string', Rule::in($this->supportedFeatures())],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'horizon' => ['nullable', 'integer', 'min:1', 'max:365'],
            'reindex_all' => ['nullable', 'boolean'],
            'dataset_upload_id' => ['nullable', 'string', 'max:191'],
            'parameters' => ['nullable', 'array'],
        ];
    }

    /**
     * @return list<string>
     */
    public function supportedFeatures(): array
    {
        return collect(ModelTrainingJob::FEATURE_CLIENT_METHODS)
            ->filter(static fn (?string $method) => $method !== null)
            ->keys()
            ->map(static fn ($feature) => (string) $feature)
            ->values()
            ->all();
    }
}
