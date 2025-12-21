<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\ApiFormRequest;
use App\Models\SupplierDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreSupplierDocumentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(SupplierDocument::DOCUMENT_TYPES)],
            'document' => ['required', 'file', 'max:51200', 'mimetypes:application/pdf,image/jpeg,image/png'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => [
                'nullable',
                'date',
                Rule::when(
                    fn (): bool => $this->filled('issued_at'),
                    'after_or_equal:issued_at'
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');

        if (is_string($type)) {
            $this->merge(['type' => strtolower($type)]);
        }
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('document');

        return $file;
    }

    public function type(): string
    {
        /** @var string $type */
        $type = $this->validated('type');

        return $type;
    }

    public function issuedAt(): ?Carbon
    {
        $value = $this->validated('issued_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function expiresAt(): ?Carbon
    {
        $value = $this->validated('expires_at');

        return $value ? Carbon::parse($value) : null;
    }
}
