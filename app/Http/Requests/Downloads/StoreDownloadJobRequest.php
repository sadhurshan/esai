<?php

namespace App\Http\Requests\Downloads;

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreDownloadJobRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::in(DownloadDocumentType::values())],
            'document_id' => ['required', 'integer', 'min:1'],
            'format' => ['required', 'string', Rule::in(DownloadFormat::values())],
            'reference' => ['nullable', 'string', 'max:120'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
