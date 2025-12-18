<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\InteractsWithDocumentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreCompanyDocumentRequest extends ApiFormRequest
{
    use InteractsWithDocumentRules;

    public function rules(): array
    {
        $extensions = $this->documentAllowedExtensions();
        $maxKilobytes = $this->documentMaxKilobytes();

        return [
            'type' => ['required', 'string', Rule::in(['registration', 'tax', 'esg', 'other'])],
            'document' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimes:'.implode(',', $extensions),
            ],
        ];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('document');

        return $file;
    }

}
