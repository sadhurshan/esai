<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\ApiFormRequest;

class AttachInvoiceFileRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = max(1, (int) config('documents.max_size_mb', 50)) * 1024;

        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$maxKilobytes],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'file' => $this->file('file'),
        ];
    }
}
