<?php

use App\Events\NotificationDispatched;
use App\Mail\NotificationMail;
use App\Models\Company;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function onboardedCompany(array $overrides = []): Company
{
    $defaults = [
        'status' => 'active',
        'registration_no' => 'REG-1001',
        'tax_id' => 'TAX-1001',
        'country' => 'US',
        'email_domain' => 'example.com',
        'primary_contact_name' => 'Jane Doe',
        'primary_contact_email' => 'jane@example.com',
        'primary_contact_phone' => '+1-555-0101',
    ];

    return Company::factory()->create(array_merge($defaults, $overrides));
}

it('dispatches immediate notifications and queues email for push and both channels', function (): void {
    Mail::fake();
    Event::fake();

    $company = onboardedCompany();

    $pushUser = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    $bothUser = User::factory()->for($company)->create([
        'role' => 'finance',
    ]);

    NotificationPreference::create([
        'user_id' => $pushUser->id,
        'event_type' => 'invoice_created',
        'channel' => 'push',
        'digest' => 'none',
    ]);

    NotificationPreference::create([
        'user_id' => $bothUser->id,
        'event_type' => 'invoice_created',
        'channel' => 'both',
        'digest' => 'none',
    ]);

    $service = app(NotificationService::class);

    $service->send([
        $pushUser,
        $bothUser,
    ], 'invoice_created', 'Invoice created', 'A new invoice has been issued.', \App\Models\Invoice::class, 1234, ['amount' => 199.95]);

    $notifications = Notification::query()->orderBy('user_id')->get();

    expect($notifications)->toHaveCount(2);
    expect($notifications->pluck('channel')->all())->toEqualCanonicalizing(['push', 'both']);

    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($pushUser): bool {
        return $mail->notification->user_id === $pushUser->id;
    });

    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($bothUser): bool {
        return $mail->notification->user_id === $bothUser->id;
    });

    Mail::assertQueuedCount(2);

    Event::assertDispatchedTimes(NotificationDispatched::class, 2);
});

it('stores notifications for digest users without queuing immediate delivery', function (): void {
    Mail::fake();
    Event::fake();

    $company = onboardedCompany();

    $user = User::factory()->for($company)->create([
        'role' => 'finance',
    ]);

    NotificationPreference::create([
        'user_id' => $user->id,
        'event_type' => 'invoice_created',
        'channel' => 'email',
        'digest' => 'daily',
    ]);

    $service = app(NotificationService::class);

    $service->send([$user], 'invoice_created', 'Invoice created', 'Daily digest notification.', \App\Models\Invoice::class, 4321);

    $notification = Notification::first();

    expect($notification)->not->toBeNull()
        ->and($notification->channel)->toBe('email')
        ->and($notification->read_at)->toBeNull();

    Mail::assertNothingQueued();
    Event::assertNotDispatched(NotificationDispatched::class);
});

it('lists notifications for the current user and supports read filters', function (): void {
    $company = onboardedCompany();

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $unread = Notification::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'event_type' => 'invoice_created',
        'title' => 'Invoice ready',
        'body' => 'An invoice needs your review.',
        'entity_type' => \App\Models\Invoice::class,
        'entity_id' => 55,
        'channel' => 'both',
        'meta' => ['amount' => 1200],
    ]);

    $read = Notification::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'event_type' => 'grn_posted',
        'title' => 'GRN posted',
        'body' => 'A goods receipt note has been posted.',
        'entity_type' => \App\Models\GoodsReceiptNote::class,
        'entity_id' => 77,
        'channel' => 'push',
        'read_at' => now(),
    ]);

    $secondUnread = Notification::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'event_type' => 'plan_overlimit',
        'title' => 'Storage threshold reached',
        'body' => 'Storage usage exceeded 90% of quota.',
        'entity_type' => \App\Models\Company::class,
        'entity_id' => $company->id,
        'channel' => 'email',
    ]);

    Notification::create([
        'company_id' => $company->id,
        'user_id' => User::factory()->for($company)->create()->id,
        'event_type' => 'po_issued',
        'title' => 'Ignore',
        'body' => 'Not for current user.',
        'entity_type' => \App\Models\PurchaseOrder::class,
        'entity_id' => 99,
        'channel' => 'push',
    ]);

    $response = $this->getJson('/api/notifications?status=unread');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonFragment(['id' => $unread->id])
        ->assertJsonFragment(['id' => $secondUnread->id])
        ->assertJsonMissing(['id' => $read->id]);

    $markResponse = $this->putJson("/api/notifications/{$unread->id}/read");
    $markResponse->assertOk()->assertJsonPath('data.read_at', fn (?string $value) => $value !== null);

    expect($unread->fresh()->read_at)->not->toBeNull();

    $allResponse = $this->postJson('/api/notifications/mark-all-read');
    $allResponse->assertOk()->assertJsonPath('data.updated', 1);

    expect($secondUnread->fresh()->read_at)->not->toBeNull();
});

it('updates notification preferences and validates payloads', function (): void {
    $company = onboardedCompany();

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $valid = $this->putJson('/api/notification-preferences', [
        'event_type' => 'invoice_created',
        'channel' => 'email',
        'digest' => 'weekly',
    ]);

    $valid->assertOk()
        ->assertJsonPath('data.event_type', 'invoice_created')
        ->assertJsonPath('data.channel', 'email')
        ->assertJsonPath('data.digest', 'weekly');

    $this->assertDatabaseHas('user_notification_prefs', [
        'user_id' => $user->id,
        'event_type' => 'invoice_created',
        'channel' => 'email',
        'digest' => 'weekly',
    ]);

    $invalid = $this->putJson('/api/notification-preferences', [
        'event_type' => 'invalid_event',
        'channel' => 'sms',
        'digest' => 'hourly',
    ]);

    $invalid->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

it('prevents users from acting on notifications belonging to others', function (): void {
    $company = onboardedCompany();

    $owner = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    $intruder = User::factory()->for($company)->create([
        'role' => 'finance',
    ]);

    $notification = Notification::create([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'event_type' => 'po_issued',
        'title' => 'PO issued',
        'body' => 'A purchase order has been issued.',
        'entity_type' => \App\Models\PurchaseOrder::class,
        'entity_id' => 12,
        'channel' => 'push',
    ]);

    actingAs($intruder);

    $response = $this->putJson("/api/notifications/{$notification->id}/read");

    $response->assertForbidden();

    $listResponse = $this->getJson('/api/notifications');

    $listResponse->assertOk()
        ->assertJsonMissing(['id' => $notification->id]);
});
