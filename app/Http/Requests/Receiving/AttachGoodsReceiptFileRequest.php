<?php

namespace App\Http\Requests\Receiving;

use App\Http\Requests\ApiFormRequest;

class AttachGoodsReceiptFileRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:25600'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
