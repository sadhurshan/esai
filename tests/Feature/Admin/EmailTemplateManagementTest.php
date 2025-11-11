<?php

use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('allows super admin to manage email templates', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    actingAs($user);

    $createResponse = postJson('/api/admin/email-templates', [
        'key' => 'rfq_awarded',
        'name' => 'RFQ Awarded',
        'subject' => 'RFQ {{ $rfq_number }} awarded',
        'body_html' => '<p>Hello {{ $name }}</p>',
        'body_text' => 'Hello {{ $name }}',
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.template.key', 'rfq_awarded');

    $templateId = $createResponse->json('data.template.id');

    $previewResponse = postJson("/api/admin/email-templates/{$templateId}/preview", [
        'data' => ['name' => 'Alex', 'rfq_number' => 'RFQ-001'],
    ]);

    $previewResponse
        ->assertOk()
        ->assertJsonPath('data.html', '<p>Hello Alex</p>');

    $updateResponse = putJson("/api/admin/email-templates/{$templateId}", [
        'enabled' => false,
    ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.template.enabled', false);

    $listResponse = getJson('/api/admin/email-templates');

    $listResponse
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $templateId);

    deleteJson("/api/admin/email-templates/{$templateId}")
        ->assertOk();

    expect(EmailTemplate::query()->whereKey($templateId)->exists())->toBeFalse();
    expect(AuditLog::count())->toBeGreaterThan(0);
});

it('forbids support admin from mutating email templates', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();

    actingAs($user);

    $template = EmailTemplate::factory()->create();

    postJson('/api/admin/email-templates', [
        'key' => 'new_template',
        'name' => 'New Template',
        'subject' => 'Subject',
        'body_html' => '<p>Hi</p>',
    ])->assertForbidden();

    putJson("/api/admin/email-templates/{$template->id}", ['name' => 'Updated'])
        ->assertForbidden();

    deleteJson("/api/admin/email-templates/{$template->id}")
        ->assertForbidden();
});

it('requires admin guard for email template routes', function (): void {
    $template = EmailTemplate::factory()->create();

    getJson('/api/admin/email-templates')->assertUnauthorized();
    postJson('/api/admin/email-templates', [])->assertUnauthorized();
    putJson("/api/admin/email-templates/{$template->id}", [])->assertUnauthorized();
    deleteJson("/api/admin/email-templates/{$template->id}")->assertUnauthorized();
});
