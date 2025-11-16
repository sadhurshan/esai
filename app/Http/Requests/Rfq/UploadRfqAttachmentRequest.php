<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;

class UploadRfqAttachmentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = max(1, (int) config('documents.max_size_mb', 50)) * 1024;

        return [
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'file' => $this->file('file'),
            'title' => $this->input('title'),
            'description' => $this->input('description'),
        ];
    }
}
