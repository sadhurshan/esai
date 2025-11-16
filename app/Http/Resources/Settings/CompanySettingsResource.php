<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'legal_name' => $this->legal_name,
            'display_name' => $this->display_name,
            'tax_id' => $this->tax_id,
            'registration_number' => $this->registration_number,
            'emails' => $this->emails ?? [],
            'phones' => $this->phones ?? [],
            'bill_to' => $this->formatAddress($this->bill_to),
            'ship_from' => $this->formatAddress($this->ship_from),
            'logo_url' => $this->logo_url,
            'mark_url' => $this->mark_url,
        ];
    }

    private function formatAddress(?array $address): ?array
    {
        if (! $address) {
            return null;
        }

        return [
            'attention' => $address['attention'] ?? null,
            'line1' => $address['line1'] ?? '',
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? '',
        ];
    }
}
