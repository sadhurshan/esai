<?php

namespace App\Http\Requests\Events;

use App\Enums\WebhookDeliveryStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class EventDeliveryIndexRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::in(array_map(static fn (WebhookDeliveryStatus $status) => $status->value, WebhookDeliveryStatus::cases()))],
            'event' => ['nullable', 'string', 'max:191'],
            'subscription_id' => ['nullable', 'integer'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'dlq_only' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'cursor' => $validated['cursor'] ?? null,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : null,
            'status' => $validated['status'] ?? null,
            'event' => $validated['event'] ?? null,
            'subscription_id' => isset($validated['subscription_id']) ? (int) $validated['subscription_id'] : null,
            'endpoint' => $validated['endpoint'] ?? null,
            'search' => $validated['search'] ?? null,
            'dlq_only' => $this->boolean('dlq_only'),
        ];
    }
}
