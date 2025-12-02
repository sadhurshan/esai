<?php

namespace App\Http\Requests\Admin;

use App\Models\DigitalTwin;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreDigitalTwinAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $twin = $this->route('digital_twin');

        return $twin instanceof DigitalTwin
            ? ($this->user()?->can('update', $twin) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSizeKb = config('digital-twins.assets.max_size_mb', 200) * 1024;
        $extensions = collect(config('digital-twins.assets.allowed_extensions', []))
            ->map(fn ($extension) => strtolower((string) $extension))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $mimeTypes = collect(config('digital-twins.assets.allowed_mimes', []))
            ->filter()
            ->values()
            ->all();

        $fileRules = ['required', 'file', 'max:'.$maxSizeKb];

        if ($mimeTypes !== []) {
            $fileRules[] = 'mimetypes:'.implode(',', $mimeTypes);
        }

        if ($extensions !== []) {
            $fileRules[] = function (string $attribute, mixed $value, Closure $fail) use ($extensions): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = strtolower((string) $value->getClientOriginalExtension());

                if ($extension === '' || ! in_array($extension, $extensions, true)) {
                    $fail('The '.$attribute.' must be a file of type: '.implode(', ', $extensions).'.');
                }
            };
        }

        return [
            'file' => $fileRules,
            'type' => ['nullable', 'string', 'max:32'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
