<?php

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Enums\DownloadJobStatus;
use App\Jobs\ProcessDownloadJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DownloadJob;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createDownloadCompany(array $overrides = []): Company
{
    $plan = Plan::factory()->create();

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $overrides));

    $customer = Customer::factory()->for($company)->create();

    Subscription::factory()->for($company)->create([
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    return $company->fresh();
}

function createDownloadUser(array $userOverrides = []): User
{
    $company = createDownloadCompany();

    return User::factory()->for($company)->create(array_merge([
        'role' => 'buyer_admin',
    ], $userOverrides));
}

function createDownloadJobFor(User $user, array $overrides = []): DownloadJob
{
    $defaults = [
        'company_id' => $user->company_id,
        'requested_by' => $user->id,
        'document_type' => DownloadDocumentType::PurchaseOrder,
        'document_id' => 1,
        'reference' => 'PO-00001',
        'format' => DownloadFormat::Pdf,
        'status' => DownloadJobStatus::Queued,
        'storage_disk' => null,
        'file_path' => null,
        'filename' => null,
        'ready_at' => null,
        'expires_at' => null,
    ];

    return DownloadJob::query()->create(array_merge($defaults, $overrides));
}

it('lists download jobs with cursor pagination metadata', function () {
    $user = createDownloadUser();
    actingAs($user);

    createDownloadJobFor($user, [
        'reference' => 'First Job',
        'status' => DownloadJobStatus::Ready,
        'created_at' => now()->subDay(),
    ]);

    createDownloadJobFor($user, [
        'reference' => 'Second Job',
        'status' => DownloadJobStatus::Failed,
        'created_at' => now(),
    ]);

    $otherUser = createDownloadUser();
    createDownloadJobFor($otherUser, ['reference' => 'Foreign']);

    $response = $this->getJson('/api/downloads?per_page=1');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonStructure(['meta' => ['cursor' => ['next_cursor', 'prev_cursor', 'has_next', 'has_prev']]])
        ->assertJsonPath('data.meta.per_page', 1);
});

it('queues download job requests via the api', function () {
    Bus::fake();
    Storage::fake('downloads');

    $user = createDownloadUser();
    actingAs($user);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $user->company_id,
    ]);

    $response = $this->postJson('/api/downloads', [
        'document_type' => DownloadDocumentType::PurchaseOrder->value,
        'document_id' => $purchaseOrder->id,
        'format' => DownloadFormat::Pdf->value,
        'reference' => 'PO-'.$purchaseOrder->id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.document_id', $purchaseOrder->id);

    $jobId = $response->json('data.id');

    Bus::assertDispatched(ProcessDownloadJob::class, fn (ProcessDownloadJob $job) => $job->downloadJobId === $jobId);
});

it('retries download jobs for authorized companies', function () {
    Bus::fake();
    Storage::fake('downloads');

    $user = createDownloadUser();
    actingAs($user);

    $job = createDownloadJobFor($user, [
        'status' => DownloadJobStatus::Failed,
        'storage_disk' => 'downloads',
        'file_path' => 'reports/error.csv',
        'filename' => 'error.csv',
    ]);

    $response = $this->postJson("/api/downloads/{$job->id}/retry");

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $job->id);

    Bus::assertDispatched(ProcessDownloadJob::class, fn (ProcessDownloadJob $queued) => $queued->downloadJobId === $job->id);
});

it('prevents retrying download jobs outside the company scope', function () {
    Bus::fake();

    $user = createDownloadUser();
    $otherUser = createDownloadUser();
    actingAs($user);

    $foreignJob = createDownloadJobFor($otherUser, [
        'status' => DownloadJobStatus::Failed,
    ]);

    $response = $this->postJson("/api/downloads/{$foreignJob->id}/retry");

    $response
        ->assertNotFound()
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Not found.');

    Bus::assertNothingDispatched();
});

it('serves signed download urls for ready jobs', function () {
    Storage::fake('downloads');

    $user = createDownloadUser();
    actingAs($user);

    $job = createDownloadJobFor($user, [
        'status' => DownloadJobStatus::Ready,
        'storage_disk' => 'downloads',
        'file_path' => 'reports/export.csv',
        'filename' => 'export.csv',
        'ready_at' => now()->subMinutes(10),
        'expires_at' => now()->addHour(),
    ]);

    Storage::disk('downloads')->put($job->file_path, 'report-data');

    $signedUrl = URL::temporarySignedRoute('downloads.file', now()->addMinutes(5), ['downloadJob' => $job->id]);

    $response = $this->get($signedUrl);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toEqual('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('export.csv');
});

it('blocks signed downloads for users outside the job company', function () {
    Storage::fake('downloads');

    $owner = createDownloadUser();
    $intruder = createDownloadUser();
    actingAs($intruder);

    $job = createDownloadJobFor($owner, [
        'status' => DownloadJobStatus::Ready,
        'storage_disk' => 'downloads',
        'file_path' => 'reports/private.csv',
        'filename' => 'private.csv',
        'ready_at' => now()->subMinutes(5),
        'expires_at' => now()->addHour(),
    ]);

    Storage::disk('downloads')->put($job->file_path, 'secret-data');

    $signedUrl = URL::temporarySignedRoute('downloads.file', now()->addMinutes(5), ['downloadJob' => $job->id]);

    $response = $this->get($signedUrl);

    $response
        ->assertNotFound()
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Not found.');
});
