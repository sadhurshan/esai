<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $event,
        public readonly array $payload,
        public readonly ?int $deliveryId = null
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $subscription = WebhookSubscription::query()->find($this->subscriptionId);

        if ($subscription === null || ! $subscription->active) {
            return;
        }

        $delivery = $this->deliveryId !== null
            ? WebhookDelivery::query()->findOrFail($this->deliveryId)
            : WebhookDelivery::create([
                'subscription_id' => $subscription->id,
                'event' => $this->event,
                'payload' => $this->payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
            ]);

        $policy = $subscription->retry_policy_json ?? ['max' => 5, 'backoff' => 'exponential', 'base_sec' => 30];
        $policy['max'] = (int) ($policy['max'] ?? 5);
        $policy['base_sec'] = (int) ($policy['base_sec'] ?? 30);
        $policy['backoff'] = $policy['backoff'] ?? 'exponential';

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => ($delivery->attempts ?? 0) + 1,
            'dispatched_at' => now(),
        ])->save();

        try {
            $payloadJson = json_encode($this->payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->handleFailure($delivery, $policy, 'Failed to encode payload: '.$exception->getMessage());

            return;
        }
        $timestamp = now()->toIso8601String();
        $eventId = (string) ($delivery->id ?? Str::uuid());
        $signature = hash_hmac('sha256', $payloadJson.$timestamp, $subscription->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-ESAI-Event' => $this->event,
                    'X-ESAI-Id' => $eventId,
                    'X-ESAI-Timestamp' => $timestamp,
                    'X-ESAI-Signature' => $signature,
                    'User-Agent' => 'ElementsSupplyAI Webhook Dispatcher',
                ])
                ->post($subscription->url, $this->payload);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Success,
                    'delivered_at' => now(),
                    'last_error' => null,
                ])->save();

                return;
            }

            $message = sprintf('HTTP %s: %s', $response->status(), Str::limit($response->body(), 255));
            $this->handleFailure($delivery, $policy, $message);
        } catch (\Throwable $exception) {
            Log::warning('Webhook dispatch failed', [
                'subscription_id' => $subscription->id,
                'delivery_id' => $delivery->id,
                'error' => $exception->getMessage(),
            ]);

            $this->handleFailure($delivery, $policy, Str::limit($exception->getMessage(), 255));
        }
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function handleFailure(WebhookDelivery $delivery, array $policy, string $message): void
    {
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Failed,
            'last_error' => $message,
        ])->save();

        if ($delivery->attempts >= ($policy['max'] ?? 5)) {
            return;
        }

        $delaySeconds = $this->calculateDelay($policy, $delivery->attempts);

        self::dispatch($delivery->subscription_id, $delivery->event, $delivery->payload ?? [], $delivery->id)
            ->delay(now()->addSeconds($delaySeconds));
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function calculateDelay(array $policy, int $attempts): int
    {
        $base = max(1, (int) ($policy['base_sec'] ?? 30));

        if (($policy['backoff'] ?? 'exponential') === 'linear') {
            return $base * $attempts;
        }

        return $base * (2 ** max(0, $attempts - 1));
    }
}
