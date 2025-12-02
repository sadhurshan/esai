<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'subscription_id' => WebhookSubscription::factory(),
            'company_id' => fn (array $attributes) => $attributes['subscription_id'] instanceof WebhookSubscription
                ? $attributes['subscription_id']->company_id
                : WebhookSubscription::query()->findOrFail($attributes['subscription_id'])->company_id,
            'event' => 'QuoteSubmitted',
            'signature' => Str::random(64),
            'payload' => ['id' => $this->faker->uuid()],
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 5,
            'latency_ms' => null,
            'response_code' => null,
            'response_body' => null,
        ];
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::Failed,
            'last_error' => 'Timeout',
        ]);
    }

    public function deadLettered(): self
    {
        return $this->state(fn () => [
            'status' => WebhookDeliveryStatus::DeadLettered,
            'dead_lettered_at' => now(),
            'last_error' => 'DLQ',
        ]);
    }
}
