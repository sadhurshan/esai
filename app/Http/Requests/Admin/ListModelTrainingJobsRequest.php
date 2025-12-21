<?php

namespace App\Http\Requests\Admin;

use App\Models\ModelTrainingJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListModelTrainingJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ModelTrainingJob::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feature' => ['nullable', 'string', Rule::in(ModelTrainingJob::allowedFeatures())],
            'status' => ['nullable', 'string', Rule::in([
                ModelTrainingJob::STATUS_PENDING,
                ModelTrainingJob::STATUS_RUNNING,
                ModelTrainingJob::STATUS_COMPLETED,
                ModelTrainingJob::STATUS_FAILED,
            ])],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'started_from' => ['nullable', 'date'],
            'started_to' => ['nullable', 'date', 'after_or_equal:started_from'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'microservice_job_id' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
