<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SelfRegistrationRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('company_domain')) {
            $this->merge([
                'company_domain' => strtolower((string) $this->input('company_domain')),
            ]);
        }

        if ($this->has('country')) {
            $this->merge([
                'country' => strtoupper((string) $this->input('country')),
            ]);
        }

        if ($this->has('website')) {
            $website = (string) $this->input('website');
            $this->merge([
                'website' => $website !== '' ? trim($website) : null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:191', Rule::unique(User::class, 'email')],
            'password' => ['required', Password::default(), 'confirmed'],
            'company_name' => ['required', 'string', 'max:160', Rule::unique('companies', 'name')],
            'company_domain' => [
                'required',
                'string',
                'max:191',
                'regex:/^(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}$/i',
                Rule::unique('companies', 'email_domain'),
            ],
            'registration_no' => ['required', 'string', 'max:120'],
            'tax_id' => ['required', 'string', 'max:120'],
            'website' => ['required', 'url', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'country' => ['nullable', 'string', 'size:2'],
            'company_documents' => ['required', 'array', 'min:1', 'max:5'],
            'company_documents.*.type' => ['required', 'string', Rule::in(['registration', 'tax', 'esg', 'other'])],
            'company_documents.*.file' => [
                'required',
                'file',
                'max:'.$this->maxFileSizeKilobytes(),
                'mimes:'.implode(',', $this->allowedExtensions()),
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, file: UploadedFile}>
     */
    public function companyDocuments(): array
    {
        $documents = $this->input('company_documents', []);

        if (! is_array($documents)) {
            return [];
        }

        $resolved = [];

        foreach ($documents as $index => $document) {
            $file = $this->file("company_documents.$index.file");

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $type = is_array($document) && isset($document['type']) ? (string) $document['type'] : null;

            if ($type === null) {
                continue;
            }

            $resolved[] = [
                'type' => $type,
                'file' => $file,
            ];
        }

        return $resolved;
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
