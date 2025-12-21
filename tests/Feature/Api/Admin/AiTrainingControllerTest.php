<?php

use App\Jobs\RunModelTrainingJob;
use App\Models\AiEvent;
use App\Models\Company;
use App\Models\ModelTrainingJob;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('starts an AI training job and records an event', function (): void {
    config(['plans.features.ai_training_enabled.plan_codes' => ['enterprise']]);

    Queue::fake();

    $plan = Plan::factory()->create(['code' => 'enterprise']);
    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = makeSuperAdmin();

    actingAs($user);

    $this->mock(AiClient::class, function ($mock): void {
        $mock->shouldReceive('trainForecast')
            ->once()
            ->andReturn([
                'job_id' => 'job_123',
                'job' => ['status' => 'pending'],
                'response' => ['job_id' => 'job_123'],
            ]);
    });

    $payload = [
        'feature' => 'forecast',
        'company_id' => $company->id,
        'start_date' => Carbon::now()->subMonth()->format('Y-m-d'),
        'end_date' => Carbon::now()->format('Y-m-d'),
        'horizon' => 30,
    ];

    $response = postJson('/api/v1/admin/ai-training/start', $payload);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.job.company_id', $company->id)
        ->assertJsonPath('data.job.microservice_job_id', 'job_123');

    $this->assertDatabaseHas('model_training_jobs', [
        'company_id' => $company->id,
        'feature' => 'forecast',
        'status' => ModelTrainingJob::STATUS_PENDING,
        'microservice_job_id' => 'job_123',
    ]);

    Queue::assertPushed(RunModelTrainingJob::class, 1);

    expect(AiEvent::query()->where('feature', 'ai_training_forecast_start')->count())->toBe(1);
});

it('lists training jobs for platform super admins', function (): void {
    $user = makeSuperAdmin();

    $companyA = Company::factory()->create(['name' => 'Signal Works']);
    $companyB = Company::factory()->create(['name' => 'Northwind Supplies']);

    $jobA = ModelTrainingJob::query()->create([
        'company_id' => $companyA->id,
        'feature' => 'forecast',
        'status' => ModelTrainingJob::STATUS_COMPLETED,
        'microservice_job_id' => 'job_111',
        'result_json' => ['mape' => 7.2],
        'started_at' => Carbon::now()->subDay(),
        'finished_at' => Carbon::now()->subHours(12),
    ]);

    $jobB = ModelTrainingJob::query()->create([
        'company_id' => $companyB->id,
        'feature' => 'risk',
        'status' => ModelTrainingJob::STATUS_RUNNING,
        'microservice_job_id' => 'job_222',
        'started_at' => Carbon::now()->subMinutes(30),
    ]);

    actingAs($user);

    $response = getJson('/api/v1/admin/ai-training/jobs');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $items = $response->json('data.items');

    expect($items)->toBeArray();
    $ids = collect($items)->pluck('id')->map(static fn ($id) => (int) $id);
    expect($ids)->toContain($jobA->id, $jobB->id);
});

it('forbids non-admins from accessing AI training endpoints', function (): void {
    $plan = Plan::factory()->create(['code' => 'enterprise']);
    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create();
    actingAs($user);

    getJson('/api/v1/admin/ai-training/jobs')->assertForbidden();

    postJson('/api/v1/admin/ai-training/start', [
        'feature' => 'forecast',
        'company_id' => $company->id,
    ])->assertForbidden();
});

function makeSuperAdmin(): User
{
    $user = User::factory()->create([
        'role' => 'platform_super',
    ]);

    PlatformAdmin::factory()->super()->for($user)->create();

    return $user;
}
