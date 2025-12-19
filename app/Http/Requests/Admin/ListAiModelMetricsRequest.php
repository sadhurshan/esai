<?php

namespace App\Http\Requests\Admin;

use App\Models\AiModelMetric;
use Illuminate\Foundation\Http\FormRequest;

class ListAiModelMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AiModelMetric::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feature' => ['nullable', 'string', 'max:191'],
            'metric_name' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
