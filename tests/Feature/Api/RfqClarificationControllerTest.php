<?php

use App\Enums\RfqClarificationType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function rfqClarificationContext(bool $inviteSupplier = true, bool $openBidding = false): array
{
    $plan = Plan::factory()->create([
        'code' => 'plan-'.Str::uuid()->toString(),
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 25,
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'trial_ends_at' => now()->addWeeks(2),
        'rfqs_monthly_used' => 0,
    ]);

    $supplierCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'trial_ends_at' => now()->addWeeks(2),
    ]);

    $buyer = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    $supplierProfile = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RFQ::STATUS_OPEN,
        'rfq_version' => 1,
        'is_open_bidding' => $openBidding,
        'due_at' => now()->addDays(5),
        'close_at' => now()->addDays(5),
    ]);

    $invitation = null;

    if ($inviteSupplier) {
        $invitation = RfqInvitation::create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierProfile->id,
            'invited_by' => $buyer->id,
            'status' => RfqInvitation::STATUS_PENDING,
        ]);
    }

    return [
        'plan' => $plan,
        'buyerCompany' => $buyerCompany,
        'buyer' => $buyer,
        'supplierCompany' => $supplierCompany,
        'supplierUser' => $supplierUser,
        'supplierProfile' => $supplierProfile,
        'rfq' => $rfq,
        'invitation' => $invitation,
    ];
}

it('allows suppliers to post questions with attachments and notifies other participants', function (): void {
    $context = rfqClarificationContext();
    $buyerCompany = $context['buyerCompany'];
    $buyer = $context['buyer'];
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

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
        ->assertJsonPath('data.message', 'Do dimensions include coating thickness?')
        ->assertJsonPath('data.attachments.0.filename', 'clarification.pdf')
        ->assertJsonPath('data.attachments.0.mime', 'application/pdf');

    expect(data_get($response->json(), 'data.attachments.0.download_url'))->not->toBeNull();

    $clarification = RfqClarification::first();

    expect($clarification)
        ->not->toBeNull()
        ->and($clarification?->type)->toBe(RfqClarificationType::Question)
        ->and($clarification?->attachmentIds())->toHaveCount(1)
        ->and($clarification?->attachmentMetadata()[0]['filename'] ?? null)->toBe('clarification.pdf')
        ->and($clarification?->company_id)->toBe($buyerCompany->id);

    expect(Document::count())->toBe(1);

    $notifiedUserIds = Notification::query()->pluck('user_id');

    expect(Notification::count())->toBe(1)
        ->and($notifiedUserIds)->toContain($buyer->id)
        ->and($notifiedUserIds)->not->toContain($supplier->id);
});

it('stores multiple clarification attachments with document metadata', function (): void {
    $context = rfqClarificationContext();
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

    config(['documents.disk' => 'local']);
    Storage::fake('local');

    actingAs($supplier);

    $response = post("/api/rfqs/{$rfq->id}/clarifications/question", [
        'message' => 'Please see the attached drawings.',
        'attachments' => [
            UploadedFile::fake()->create('drawing-a.pdf', 90, 'application/pdf'),
            UploadedFile::fake()->create('diagram.png', 32, 'image/png'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data.attachments')
        ->assertJsonPath('data.attachments.0.filename', 'drawing-a.pdf')
        ->assertJsonPath('data.attachments.1.mime', 'image/png');

    $clarification = RfqClarification::first();

    expect($clarification)
        ->not->toBeNull()
        ->and($clarification?->attachmentIds())->toHaveCount(2)
        ->and($clarification?->attachmentMetadata()[1]['filename'] ?? null)->toBe('diagram.png');

    expect(Document::count())->toBe(2);
});

it('increments RFQ version and logs amendment details when buyers post amendments', function (): void {
    $context = rfqClarificationContext();
    $buyerCompany = $context['buyerCompany'];
    $buyer = $context['buyer'];
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

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

    expect($rfq->rfq_version)->toBe(2)
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
    $context = rfqClarificationContext();
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

    actingAs($supplier);

    post("/api/rfqs/{$rfq->id}/clarifications/answer", [
        'message' => 'Answer from supplier should be rejected.',
    ])->assertForbidden();

    post("/api/rfqs/{$rfq->id}/clarifications/amendment", [
        'message' => 'Unauthorized amendment attempt.',
    ])->assertForbidden();
});

it('lists clarifications in chronological order', function (): void {
    $context = rfqClarificationContext();
    $buyer = $context['buyer'];
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

    $first = RfqClarification::factory()
        ->for($context['buyerCompany'])
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
        ->for($context['buyerCompany'])
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
        ->for($context['buyerCompany'])
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

it('blocks uninvited suppliers from viewing or posting clarifications on private rfqs', function (): void {
    $context = rfqClarificationContext(inviteSupplier: false);
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

    actingAs($supplier);

    post("/api/rfqs/{$rfq->id}/clarifications/question", [
        'message' => 'Attempting to bypass invite requirements.',
    ])->assertForbidden();

    getJson("/api/rfqs/{$rfq->id}/clarifications")
        ->assertForbidden();
});

it('lets supplier users in open bidding rfqs ask questions without invitations', function (): void {
    $context = rfqClarificationContext(inviteSupplier: false, openBidding: true);
    $buyer = $context['buyer'];
    $supplier = $context['supplierUser'];
    $buyerCompany = $context['buyerCompany'];
    $rfq = $context['rfq'];

    actingAs($supplier);

    post("/api/rfqs/{$rfq->id}/clarifications/question", [
        'message' => 'Question from open bidding supplier.',
    ])->assertCreated();

    $clarification = RfqClarification::first();

    expect($clarification)
        ->not->toBeNull()
        ->and($clarification?->company_id)->toBe($buyerCompany->id);

    actingAs($buyer);

    $notifiedUserIds = Notification::query()->pluck('user_id');

    expect($notifiedUserIds)->toContain($buyer->id);
});

it('blocks clarification postings when the rfq deadline has passed', function (): void {
    $context = rfqClarificationContext();
    $supplier = $context['supplierUser'];
    $rfq = $context['rfq'];

    $rfq->update([
        'due_at' => now()->subHour(),
        'status' => RFQ::STATUS_OPEN,
    ]);

    actingAs($supplier);

    post("/api/rfqs/{$rfq->id}/clarifications/question", [
        'message' => 'Trying to post after deadline.',
    ])->assertStatus(409)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.rfq.0', fn ($value) => str_contains($value, 'deadline'));
});
