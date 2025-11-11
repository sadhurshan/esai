<?php

namespace App\Services\Admin;

use App\Jobs\DispatchWebhookJob;
use App\Models\Company;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WebhookService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSubscription(array $attributes): WebhookSubscription
    {
        $payload = [
            'company_id' => $attributes['company_id'],
            'url' => $attributes['url'],
            'secret' => $attributes['secret'] ?? Str::random(64),
            'events' => Arr::wrap($attributes['events']),
            'active' => $attributes['active'] ?? true,
            'retry_policy_json' => $this->normalisePolicy($attributes['retry_policy_json'] ?? null),
        ];

        $subscription = WebhookSubscription::create($payload);

        $this->auditLogger->created($subscription);

        return $subscription->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSubscription(WebhookSubscription $subscription, array $attributes): WebhookSubscription
    {
        $before = Arr::only($subscription->getOriginal(), ['url', 'secret', 'events', 'active', 'retry_policy_json']);

        $payload = Arr::only($attributes, ['url', 'secret', 'events', 'active', 'retry_policy_json', 'company_id']);

        if (array_key_exists('retry_policy_json', $payload)) {
            $payload['retry_policy_json'] = $this->normalisePolicy($payload['retry_policy_json']);
        }

        if (array_key_exists('events', $payload)) {
            $payload['events'] = Arr::wrap($payload['events']);
        }

        $subscription->fill($payload)->save();

        $subscription->refresh();

        $this->auditLogger->updated($subscription, $before, Arr::only($subscription->attributesToArray(), array_keys($before)));

        return $subscription;
    }

    public function deleteSubscription(WebhookSubscription $subscription): void
    {
        $before = Arr::only($subscription->attributesToArray(), ['url', 'events']);

        $subscription->delete();

        $this->auditLogger->deleted($subscription, $before);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function emit(Company $company, string $event, array $payload = []): void
    {
        $subscriptions = WebhookSubscription::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($subscriptions as $subscription) {
            DispatchWebhookJob::dispatch($subscription->id, $event, $payload);
        }
    }

    public function retryDelivery(WebhookDelivery $delivery): void
    {
        DispatchWebhookJob::dispatch($delivery->subscription_id, $delivery->event, $delivery->payload ?? [], $delivery->id);
    }

    /**
     * @param  array<string, mixed>|null  $policy
     * @return array<string, mixed>
     */
    private function normalisePolicy(?array $policy): array
    {
        $policy ??= [];

        return [
            'max' => (int) ($policy['max'] ?? 5),
            'backoff' => $policy['backoff'] ?? 'exponential',
            'base_sec' => (int) ($policy['base_sec'] ?? 30),
        ];
    }
}
