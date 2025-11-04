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
            'document' => ['required', 'file', 'max:5120', 'mimetypes:application/pdf,image/jpeg,image/png'], // TODO: clarify allowed mime types and size limits with spec
        ];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('document');

        return $file;
    }
}
