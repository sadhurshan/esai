<?php

use App\Enums\ScrapedSupplierStatus;
use App\Enums\SupplierScrapeJobStatus;
use App\Jobs\PollSupplierScrapeJob;
use App\Models\AiEvent;
use App\Models\PlatformAdmin;
use App\Models\ScrapedSupplier;
use App\Models\Supplier;
use App\Models\SupplierScrapeJob;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\SupplierScrapeService;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

it('forbids non platform admins from starting supplier scrape jobs', function (): void {
    $company = createSubscribedCompany();

    $user = User::factory()->create(['role' => 'buyer_admin']);
    actingAs($user);

    postJson('/api/v1/admin/supplier-scrapes/start', [
        'company_id' => $company->id,
        'query' => 'precision cnc',
        'region' => 'US',
        'max_results' => 5,
    ])->assertForbidden();
});

it('starts supplier scrape job and dispatches poller', function (): void {
    $company = createSubscribedCompany();
    $admin = createPlatformSuperAdmin();
    actingAs($admin);

    Queue::fake();

    $this->mock(AiClient::class, static function (MockInterface $mock): void {
        $mock->shouldReceive('scrapeSuppliers')
            ->once()
            ->andReturn([
                'job_id' => 'remote-job-42',
                'job' => ['status' => 'pending'],
                'response' => ['job_id' => 'remote-job-42'],
            ]);
    });

    $payload = [
        'company_id' => $company->id,
        'query' => 'precision cnc',
        'region' => 'US-West',
        'max_results' => 10,
    ];

    $response = postJson('/api/v1/admin/supplier-scrapes/start', $payload);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.job.query', 'precision cnc');

    $this->assertDatabaseHas('supplier_scrape_jobs', [
        'company_id' => $company->id,
        'query' => 'precision cnc',
        'region' => 'US-West',
        'status' => SupplierScrapeJobStatus::Pending->value,
        'parameters_json->remote_job_id' => 'remote-job-42',
    ]);

    Queue::assertPushed(PollSupplierScrapeJob::class, static function (PollSupplierScrapeJob $queuedJob) use ($admin): bool {
        return $queuedJob->scrapeJob->user_id === $admin->id && $queuedJob->scrapeJob->query === 'precision cnc';
    });
});

it('allows super admins to start supplier scrapes without a tenant scope', function (): void {
    $admin = createPlatformSuperAdmin();
    actingAs($admin);

    Queue::fake();

    $this->mock(AiClient::class, static function (MockInterface $mock): void {
        $mock->shouldReceive('scrapeSuppliers')
            ->once()
            ->andReturn([
                'job_id' => 'remote-job-55',
                'job' => ['status' => 'pending'],
            ]);
    });

    $response = postJson('/api/v1/admin/supplier-scrapes/start', [
        'query' => 'aerospace machining',
        'max_results' => 5,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('supplier_scrape_jobs', [
        'query' => 'aerospace machining',
        'company_id' => null,
    ]);
});

it('persists scraped suppliers when remote job completes', function (): void {
    $company = createSubscribedCompany();
    $admin = createPlatformSuperAdmin();

    $job = CompanyContext::forCompany($company->id, static function () use ($admin) {
        return SupplierScrapeJob::query()->create([
            'user_id' => $admin->id,
            'query' => 'cnc machining',
            'region' => 'CA',
            'status' => SupplierScrapeJobStatus::Running,
            'parameters_json' => [
                'remote_job_id' => 'remote-job-101',
                'max_results' => 5,
            ],
        ]);
    });

    $startedAt = Carbon::now()->subMinutes(2)->toIso8601String();
    $finishedAt = Carbon::now()->subMinute()->toIso8601String();

    $this->mock(AiClient::class, static function (MockInterface $mock) use ($startedAt, $finishedAt): void {
        $mock->shouldReceive('getScrapeJob')
            ->once()
            ->with('remote-job-101')
            ->andReturn([
                'job' => [
                    'status' => 'completed',
                    'result_count' => 1,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                ],
            ]);

        $mock->shouldReceive('getScrapeJobResults')
            ->once()
            ->with('remote-job-101', 0, 25)
            ->andReturn([
                'items' => [
                    [
                        'name' => 'Omega Plastics',
                        'website' => 'https://omega.example',
                        'description' => 'Precision molding',
                        'industry_tags' => ['aerospace'],
                        'city' => 'San Jose',
                        'country' => 'US',
                        'source_url' => 'https://omega.example/about',
                        'confidence' => 0.87,
                        'metadata_json' => ['source' => 'llm'],
                    ],
                ],
                'meta' => [],
            ]);
    });

    $service = app(SupplierScrapeService::class);
    $service->refreshScrapeStatus($job->fresh());

    $scraped = ScrapedSupplier::query()->first();

    expect($scraped)->not()->toBeNull();
    expect($scraped->name)->toBe('Omega Plastics');
    expect($scraped->website)->toBe('https://omega.example');
    expect($scraped->industry_tags)->toBe(['aerospace']);
    expect($scraped->confidence)->toBe('0.87');
    expect($scraped->status)->toBe(ScrapedSupplierStatus::Pending);
    expect($job->fresh()->status)->toBe(SupplierScrapeJobStatus::Completed);
});

it('approves scraped suppliers into production suppliers and logs events', function (): void {
    $company = createSubscribedCompany();
    $admin = createPlatformSuperAdmin();

    $job = CompanyContext::forCompany($company->id, static function () use ($admin) {
        return SupplierScrapeJob::query()->create([
            'user_id' => $admin->id,
            'query' => 'sheet metal',
            'status' => SupplierScrapeJobStatus::Completed,
            'parameters_json' => [
                'remote_job_id' => 'remote-job-202',
            ],
        ]);
    });

    $scraped = CompanyContext::forCompany($company->id, static function () use ($job) {
        return ScrapedSupplier::query()->create([
            'scrape_job_id' => $job->id,
            'name' => 'Legacy Metals',
            'website' => 'https://legacy.example',
            'status' => ScrapedSupplierStatus::Pending,
            'industry_tags' => ['aerospace'],
            'product_summary' => 'Legacy summary',
        ]);
    });

    actingAs($admin);

    $payload = [
        'name' => 'Legacy Metals LLC',
        'website' => 'https://legacy.example',
        'email' => 'hello@legacy.example',
        'phone' => '+1-555-1111',
        'address' => '123 Alloy Way',
        'city' => 'Austin',
        'country' => 'US',
        'capabilities' => [
            'methods' => ['CNC'],
            'industries' => ['Aerospace'],
        ],
        'certifications' => ['ISO 9001'],
        'product_summary' => 'High-mix precision metal work',
        'notes' => 'Looks production ready',
    ];

    $response = postJson("/api/v1/admin/scraped-suppliers/{$scraped->id}/approve", $payload);

    $response
        ->assertOk()
        ->assertJsonPath('data.scraped_supplier.status', ScrapedSupplierStatus::Approved->value);

    $scraped->refresh();
    $supplier = Supplier::query()->find($scraped->approved_supplier_id);

    expect($scraped->status)->toBe(ScrapedSupplierStatus::Approved);
    expect($scraped->review_notes)->toBe('Looks production ready');
    expect($supplier)->not()->toBeNull();
    expect($supplier->name)->toBe('Legacy Metals LLC');
    expect($supplier->capabilities['methods'])->toBe(['CNC']);

    expect(AiEvent::query()->where('feature', 'supplier_scrape_approve')->count())->toBe(1);
});

it('discards scraped suppliers without creating production records', function (): void {
    $company = createSubscribedCompany();
    $admin = createPlatformSuperAdmin();

    $job = CompanyContext::forCompany($company->id, static function () use ($admin) {
        return SupplierScrapeJob::query()->create([
            'user_id' => $admin->id,
            'query' => 'plastics',
            'status' => SupplierScrapeJobStatus::Completed,
            'parameters_json' => [
                'remote_job_id' => 'remote-job-303',
            ],
        ]);
    });

    $scraped = CompanyContext::forCompany($company->id, static function () use ($job) {
        return ScrapedSupplier::query()->create([
            'scrape_job_id' => $job->id,
            'name' => 'Apex Plastics',
            'status' => ScrapedSupplierStatus::Pending,
        ]);
    });

    actingAs($admin);

    deleteJson("/api/v1/admin/scraped-suppliers/{$scraped->id}", [
        'notes' => 'Insufficient data',
    ])->assertOk();

    $scraped->refresh();

    expect($scraped->status)->toBe(ScrapedSupplierStatus::Discarded);
    expect($scraped->approved_supplier_id)->toBeNull();
    expect(Supplier::query()->count())->toBe(0);
    expect(AiEvent::query()->where('feature', 'supplier_scrape_discard')->count())->toBe(1);
});

function createPlatformSuperAdmin(): User
{
    $user = User::factory()->create([
        'role' => 'platform_super',
    ]);

    PlatformAdmin::factory()->super()->for($user)->create();

    return $user;
}
