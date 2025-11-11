<?php

namespace App\Http\Requests\Admin;

use App\Models\WebhookDelivery;
use Illuminate\Foundation\Http\FormRequest;

class RetryWebhookDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        return $delivery instanceof WebhookDelivery
            ? ($this->user()?->can('retry', $delivery) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
