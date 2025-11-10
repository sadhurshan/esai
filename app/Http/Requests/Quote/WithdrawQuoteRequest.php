<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;

class WithdrawQuoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3'],
        ];
    }

    /**
     * @return array{reason: string}
     */
    public function payload(): array
    {
        /** @var array{reason: string} $data */
        $data = $this->validated();

        return $data;
    }
}
