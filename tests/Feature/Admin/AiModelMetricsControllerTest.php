<?php

use App\Models\AiModelMetric;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns filtered ai model metrics for platform admins', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();
    actingAs($user);

    $company = Company::factory()->create();
    $now = Carbon::parse('2025-12-18 12:00:00', 'UTC');

    CompanyContext::bypass(function () use ($company, $now): void {
        AiModelMetric::query()->create([
            'company_id' => $company->id,
            'feature' => 'forecast',
            'entity_type' => 'part',
            'entity_id' => 42,
            'metric_name' => 'mape',
            'metric_value' => 0.32,
            'window_start' => $now->copy()->subDays(10),
            'window_end' => $now->copy()->subDays(5),
            'notes' => ['snapshot_id' => 10],
        ]);

        AiModelMetric::query()->create([
            'company_id' => $company->id,
            'feature' => 'supplier_risk',
            'entity_type' => 'supplier_bucket',
            'entity_id' => null,
            'metric_name' => 'risk_bucket_late_rate_high',
            'metric_value' => 0.65,
            'window_start' => $now->copy()->subDays(4),
            'window_end' => $now->copy()->subDays(1),
            'notes' => ['bucket' => 'high'],
        ]);
    });

    $query = http_build_query([
        'feature' => 'forecast',
        'from' => $now->copy()->subDays(8)->toIso8601String(),
    ], '', '&', PHP_QUERY_RFC3986);

    $response = getJson('/api/admin/ai-model-metrics?'.$query);

    $response
        ->assertOk()
        ->assertJsonPath('data.items.0.feature', 'forecast')
        ->assertJsonPath('data.items.0.metric_name', 'mape')
        ->assertJsonPath('data.items.0.metric_value', 0.32)
        ->assertJsonPath('data.items.0.notes.snapshot_id', 10)
        ->assertJsonPath('meta.cursor.next_cursor', null);
});

it('requires admin authentication', function (): void {
    getJson('/api/admin/ai-model-metrics')->assertUnauthorized();

    $user = User::factory()->create();
    actingAs($user);

    getJson('/api/admin/ai-model-metrics')->assertForbidden();
});
