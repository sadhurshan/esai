<?php

namespace App\Http\Requests\Admin;

use App\Models\WebhookSubscription;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WebhookSubscription::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'url' => ['required', 'url'],
            'secret' => ['nullable', 'string', 'min:16', 'max:128'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:191'],
            'active' => ['sometimes', 'boolean'],
            'retry_policy_json' => ['sometimes', 'array'],
        ];
    }
}
