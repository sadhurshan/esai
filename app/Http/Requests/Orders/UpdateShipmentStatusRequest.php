<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['in_transit', 'delivered'])],
            'delivered_at' => ['nullable', 'date', 'required_if:status,delivered'],
        ];
    }

    public function status(): string
    {
        return (string) $this->validated()['status'];
    }

    public function deliveredAt(): ?string
    {
        return $this->validated()['delivered_at'] ?? null;
    }
}
