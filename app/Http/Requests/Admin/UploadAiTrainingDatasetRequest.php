<?php

namespace App\Http\Requests\Admin;

use App\Models\ModelTrainingJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UploadAiTrainingDatasetRequest extends FormRequest
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
        $extensions = config('ai_training.allowed_file_types', ['csv', 'jsonl', 'zip']);
        $maxKilobytes = (int) config('documents.max_size_mb', 50) * 1024;

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'dataset' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                function (string $attribute, mixed $value, callable $fail) use ($extensions): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('Dataset upload is invalid.');
                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension() ?: $value->extension() ?: '');
                    if ($extension === '' || ! in_array($extension, $extensions, true)) {
                        $fail('Dataset file type is not allowed.');
                    }
                },
            ],
        ];
    }

    public function dataset(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('dataset');

        return $file;
    }
}
