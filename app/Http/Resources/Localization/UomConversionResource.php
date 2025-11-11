<?php

namespace App\Http\Resources\Localization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UomConversionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from_code' => $this->whenLoaded('from', fn () => $this->from->code),
            'to_code' => $this->whenLoaded('to', fn () => $this->to->code),
            'factor' => (string) $this->factor,
            'offset' => (string) $this->offset,
            'dimension' => $this->whenLoaded('from', fn () => $this->from->dimension),
        ];
    }
}
