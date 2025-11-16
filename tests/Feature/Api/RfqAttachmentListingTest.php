<?php

use App\Enums\CompanyStatus;
use App\Enums\DocumentKind;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createPlanCompany(): Company
{
    $plan = Plan::firstOrCreate(
        ['code' => 'starter'],
        Plan::factory()->make(['code' => 'starter'])->getAttributes()
    );

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    return $company;
}

it('lists RFQ attachments for the owning company', function (): void {
    $company = createPlanCompany();

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'created_by' => $owner->id,
    ]);

    RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
    ]);

    Document::factory()->count(2)->create([
        'company_id' => $company->id,
        'documentable_type' => $rfq->getMorphClass(),
        'documentable_id' => $rfq->id,
        'kind' => DocumentKind::Rfq->value,
    ]);

    actingAs($owner);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/attachments");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');
});

it('forbids RFQ attachments for another company', function (): void {
    $company = createPlanCompany();
    $otherCompany = createPlanCompany();

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($otherCompany)->create([
        'created_by' => User::factory()->owner()->create([
            'company_id' => $otherCompany->id,
        ])->id,
    ]);

    actingAs($owner);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/attachments");

    $response->assertForbidden();
});

it('uploads an attachment for the owning company', function (): void {
    config()->set('documents.disk', 's3');
    Storage::fake('s3');

    $company = createPlanCompany();

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'created_by' => $owner->id,
    ]);

    actingAs($owner);

    $file = UploadedFile::fake()->create('rfq-spec.pdf', 256, 'application/pdf');

    $response = $this->post(
        "/api/rfqs/{$rfq->id}/attachments",
        [
            'file' => $file,
            'title' => 'Spec Sheet',
            'description' => 'Detailed RFQ specification',
        ],
        ['Accept' => 'application/json']
    );

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.filename', 'rfq-spec.pdf')
        ->assertJsonPath('data.uploaded_by.name', $owner->name);

    $documentId = (int) $response->json('data.document_id');

    $this->assertDatabaseHas('documents', [
        'id' => $documentId,
        'documentable_type' => $rfq->getMorphClass(),
        'documentable_id' => $rfq->id,
        'company_id' => $company->id,
        'kind' => DocumentKind::Rfq->value,
    ]);
});

it('forbids RFQ attachment uploads for another company', function (): void {
    config()->set('documents.disk', 's3');
    Storage::fake('s3');

    $company = createPlanCompany();
    $otherCompany = createPlanCompany();

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($otherCompany)->create([
        'created_by' => User::factory()->owner()->create([
            'company_id' => $otherCompany->id,
        ])->id,
    ]);

    actingAs($owner);

    $file = UploadedFile::fake()->create('rfq-spec.pdf', 256, 'application/pdf');

    $response = $this->post(
        "/api/rfqs/{$rfq->id}/attachments",
        [
            'file' => $file,
        ],
        ['Accept' => 'application/json']
    );

    $response->assertForbidden();
});
