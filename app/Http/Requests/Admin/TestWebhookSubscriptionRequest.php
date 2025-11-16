<?php

namespace App\Http\Requests\Admin;

use App\Models\WebhookSubscription;
use Illuminate\Foundation\Http\FormRequest;

class TestWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WebhookSubscription|null $subscription */
        $subscription = $this->route('webhook_subscription');

        return $subscription !== null && ($this->user()?->can('update', $subscription) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:191'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
