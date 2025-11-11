<?php

namespace App\Http\Requests\Admin;

use App\Models\WebhookSubscription;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('webhook_subscription');

        return $subscription instanceof WebhookSubscription
            ? ($this->user()?->can('update', $subscription) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'exists:companies,id'],
            'url' => ['sometimes', 'url'],
            'secret' => ['sometimes', 'string', 'min:16', 'max:128'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'max:191'],
            'active' => ['sometimes', 'boolean'],
            'retry_policy_json' => ['sometimes', 'array'],
        ];
    }
}
