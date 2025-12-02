<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreCompanyDocumentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['registration', 'tax', 'esg', 'other'])],
            'document' => [
                'required',
                'file',
                'max:'.$this->maxFileSizeKilobytes(),
                'mimes:'.implode(',', $this->allowedExtensions()),
            ],
        ];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('document');

        return $file;
    }

        /**
         * @return list<string>
         */
        private function allowedExtensions(): array
        {
            $extensions = config('documents.allowed_extensions', []);

            $normalized = array_values(array_filter(array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                is_array($extensions) ? $extensions : []
            ), static fn (string $value): bool => $value !== ''));

            return $normalized === [] ? ['pdf'] : $normalized;
        }

        private function maxFileSizeKilobytes(): int
        {
            $maxSizeMb = (int) config('documents.max_size_mb', 50);

            return max(1, $maxSizeMb) * 1024;
        }
}
