<?php

namespace App\Http\Resources\Localization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'dimension' => $this->dimension,
            'symbol' => $this->symbol,
            'si_base' => (bool) $this->si_base,
        ];
    }
}
