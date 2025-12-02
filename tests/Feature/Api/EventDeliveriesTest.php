<?php

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DispatchWebhookJob;
use App\Models\Company;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function eventDeliveryCompany(array $overrides = []): Company
{
    $defaults = [
        'status' => 'active',
        'registration_no' => 'REG-2001',
        'tax_id' => 'TAX-2001',
        'country' => 'US',
        'email_domain' => 'acme.example',
        'primary_contact_name' => 'Delivery Owner',
        'primary_contact_email' => 'owner@example.com',
        'primary_contact_phone' => '+1-555-0100',
    ];

    return Company::factory()->create(array_merge($defaults, $overrides));
}

it('lists event deliveries scoped to the authenticated company', function (): void {
    $company = eventDeliveryCompany();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);
    actingAs($user);

    $subscription = WebhookSubscription::factory()->for($company)->create();

    $visible = WebhookDelivery::factory()
        ->for($subscription, 'subscription')
        ->create([
            'company_id' => $company->id,
            'status' => WebhookDeliveryStatus::Failed,
            'last_error' => 'Timeout',
        ]);

    WebhookDelivery::factory()->create();

    $response = $this->getJson('/api/events/deliveries?status=failed');

    $response->assertOk()
        ->assertJsonPath('data.items.0.id', $visible->id)
        ->assertJsonPath('data.items.0.status', WebhookDeliveryStatus::Failed->value)
        ->assertJsonPath('data.meta.per_page', 25);
});

    it('denies event delivery access for read-only roles', function (): void {
        $company = eventDeliveryCompany();
        $readOnlyUser = User::factory()->for($company)->create(['role' => 'buyer_member']);

        actingAs($readOnlyUser);

        $response = $this->getJson('/api/events/deliveries');

        $response->assertForbidden()
        ->assertJsonPath('message', 'Events access requires integration permissions.');
    });

it('retries a delivery for the owning company', function (): void {
    Bus::fake();

    $company = eventDeliveryCompany();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);
    actingAs($user);

    $subscription = WebhookSubscription::factory()->for($company)->create();

    $delivery = WebhookDelivery::factory()
        ->for($subscription, 'subscription')
        ->create([
            'company_id' => $company->id,
            'status' => WebhookDeliveryStatus::DeadLettered,
            'dead_lettered_at' => now(),
            'attempts' => 5,
        ]);

    $response = $this->postJson("/api/events/deliveries/{$delivery->id}/retry");

    $response->assertOk()->assertJsonPath('data.id', $delivery->id);

    Bus::assertDispatched(DispatchWebhookJob::class, fn (DispatchWebhookJob $job): bool => $job->deliveryId === $delivery->id);

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->dead_lettered_at)->toBeNull()
        ->and($delivery->attempts)->toBe(0);
});

it('prevents retrying deliveries from another company', function (): void {
    $company = eventDeliveryCompany();
    $intruder = User::factory()->for($company)->create(['role' => 'buyer_admin']);
    actingAs($intruder);

    $otherCompany = eventDeliveryCompany(['email_domain' => 'other.example']);
    $subscription = WebhookSubscription::factory()->for($otherCompany)->create();

    $foreignDelivery = WebhookDelivery::factory()->for($subscription, 'subscription')->create([
        'company_id' => $otherCompany->id,
    ]);

    $response = $this->postJson("/api/events/deliveries/{$foreignDelivery->id}/retry");

    $response->assertNotFound();
});

it('replays dead-lettered deliveries for the company and ignores others', function (): void {
    Bus::fake();

    $company = eventDeliveryCompany();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);
    actingAs($user);

    $subscription = WebhookSubscription::factory()->for($company)->create();

    $first = WebhookDelivery::factory()->deadLettered()->for($subscription, 'subscription')->create([
        'company_id' => $company->id,
        'attempts' => 5,
    ]);

    $second = WebhookDelivery::factory()->deadLettered()->for($subscription, 'subscription')->create([
        'company_id' => $company->id,
        'attempts' => 4,
    ]);

    $other = WebhookDelivery::factory()->deadLettered()->create();

    $response = $this->postJson('/api/events/dlq/replay', [
        'ids' => [$first->id, $second->id, $other->id],
    ]);

    $response->assertOk()->assertJsonPath('data.replayed', 2);

    Bus::assertDispatchedTimes(DispatchWebhookJob::class, 2);

    $first->refresh();
    $second->refresh();

    expect($first->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($first->dead_lettered_at)->toBeNull()
        ->and($second->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($second->dead_lettered_at)->toBeNull();
});
