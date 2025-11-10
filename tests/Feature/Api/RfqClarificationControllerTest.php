<?php

use App\Enums\RfqClarificationType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function rfqClarificationContext(): array
{
    $plan = Plan::factory()->create([
        'code' => 'plan-'.Str::uuid()->toString(),
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 25,
    ]);

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'trial_ends_at' => now()->addWeeks(2),
        'rfqs_monthly_used' => 0,
    ]);

    $buyer = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $supplier = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'supplier_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => 'open',
        'version' => 1,
        'version_no' => 1,
    ]);

    return [$plan, $company, $buyer, $supplier, $rfq];
}

it('allows suppliers to post questions with attachments and notifies other participants', function (): void {
    [$plan, $company, $buyer, $supplier, $rfq] = rfqClarificationContext();

    config(['documents.disk' => 'local']);
    Storage::fake('local');

    actingAs($supplier);

    $response = post("/api/rfqs/{$rfq->id}/clarifications/question", [
        'message' => 'Do dimensions include coating thickness?',
        'attachments' => [
            UploadedFile::fake()->create('clarification.pdf', 120, 'application/pdf'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'question')
        ->assertJsonPath('data.message', 'Do dimensions include coating thickness?');

    $clarification = RfqClarification::first();

    expect($clarification)
        ->not->toBeNull()
        ->and($clarification?->type)->toBe(RfqClarificationType::Question)
        ->and($clarification?->attachmentIds())->toHaveCount(1)
        ->and($clarification?->company_id)->toBe($company->id);

    expect(Document::count())->toBe(1);

    $notifiedUserIds = Notification::query()->pluck('user_id');

    expect(Notification::count())->toBe(1)
        ->and($notifiedUserIds)->toContain($buyer->id)
        ->and($notifiedUserIds)->not->toContain($supplier->id);
});

it('increments RFQ version and logs amendment details when buyers post amendments', function (): void {
    [$plan, $company, $buyer, $supplier, $rfq] = rfqClarificationContext();

    config(['documents.disk' => 'local']);
    Storage::fake('local');

    actingAs($buyer);

    $response = post("/api/rfqs/{$rfq->id}/clarifications/amendment", [
        'message' => 'Revised tolerance to ±0.002 in and added updated drawings.',
        'attachments' => [
            UploadedFile::fake()->create('revision.pdf', 200, 'application/pdf'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'amendment')
        ->assertJsonPath('data.version_increment', true);

    $rfq->refresh();

    expect($rfq->version_no)->toBe(2)
        ->and($rfq->current_revision_id)->not->toBeNull();

    $clarification = RfqClarification::first();

    expect($clarification)
        ->not->toBeNull()
        ->and($clarification?->version_increment)->toBeTrue()
        ->and($clarification?->version_no)->toBe(2);

    $notifiedUserIds = Notification::query()->pluck('user_id');

    expect(Notification::count())->toBe(1)
        ->and($notifiedUserIds)->toContain($supplier->id)
        ->and($notifiedUserIds)->not->toContain($buyer->id);

    expect(Document::count())->toBe(1);

    expect(AuditLog::query()->where('entity_type', RFQ::class)->count())->toBeGreaterThanOrEqual(1);
});

it('prevents unauthorized users from posting answers or amendments', function (): void {
    [$plan, $company, $buyer, $supplier, $rfq] = rfqClarificationContext();

    actingAs($supplier);

    post("/api/rfqs/{$rfq->id}/clarifications/answer", [
        'message' => 'Answer from supplier should be rejected.',
    ])->assertForbidden();

    post("/api/rfqs/{$rfq->id}/clarifications/amendment", [
        'message' => 'Unauthorized amendment attempt.',
    ])->assertForbidden();
});

it('lists clarifications in chronological order', function (): void {
    [$plan, $company, $buyer, $supplier, $rfq] = rfqClarificationContext();

    $first = RfqClarification::factory()
        ->for($company)
        ->for($rfq, 'rfq')
        ->for($supplier, 'user')
        ->create([
            'type' => RfqClarificationType::Question,
            'message' => 'Initial supplier question',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
            'version_no' => 1,
        ]);

    $second = RfqClarification::factory()
        ->for($company)
        ->for($rfq, 'rfq')
        ->for($buyer, 'user')
        ->create([
            'type' => RfqClarificationType::Answer,
            'message' => 'Buyer response',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
            'version_no' => 1,
        ]);

    $third = RfqClarification::factory()
        ->for($company)
        ->for($rfq, 'rfq')
        ->for($buyer, 'user')
        ->amendment(2)
        ->create([
            'message' => 'RFQ amended with tighter tolerance',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

    actingAs($buyer);

    $response = getJson("/api/rfqs/{$rfq->id}/clarifications");

    $response->assertOk()
        ->assertJsonPath('data.items.0.id', $first->id)
        ->assertJsonPath('data.items.1.id', $second->id)
        ->assertJsonPath('data.items.2.id', $third->id);
});
