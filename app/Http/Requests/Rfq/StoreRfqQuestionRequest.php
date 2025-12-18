<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\InteractsWithDocumentRules;

class StoreRfqQuestionRequest extends ApiFormRequest
{
    use InteractsWithDocumentRules;

    public function rules(): array
    {
        $extensions = $this->documentAllowedExtensions();
        $maxKilobytes = $this->documentMaxKilobytes();

        return [
            'message' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:'.implode(',', $extensions), 'max:'.$maxKilobytes],
        ];
    }
}
