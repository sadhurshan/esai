<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\RoleTemplate;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\AnalyticsService;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as M;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
	ActivePersonaContext::clear();
	app()->forgetInstance(AnalyticsService::class);
	app()->forgetInstance(AiClient::class);
});

afterEach(function (): void {
	M::close();
	ActivePersonaContext::clear();
});

function analyticsReportContext(string $role = 'owner'): array {
	$plan = Plan::factory()->create([
		'code' => 'community',
		'price_usd' => null,
		'analytics_enabled' => true,
	]);

	$company = Company::factory()->create([
		'plan_id' => $plan->id,
		'plan_code' => 'community',
	]);

	$user = User::factory()->create([
		'company_id' => $company->id,
		'role' => $role,
	]);

	return compact('plan', 'company', 'user');
}

function seedRoleTemplatePermissions(string $slug, array $permissions): void {
	RoleTemplate::query()->updateOrCreate(
		['slug' => $slug],
		[
			'name' => ucfirst(str_replace('_', ' ', $slug)),
			'description' => 'Test role template',
			'permissions' => $permissions,
			'is_system' => false,
		]
	);

	app(PermissionRegistry::class)->forgetRoleCache($slug);
}

function analyticsQueryString(array $params): string {
	return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

test('forecast report endpoint returns structured report data with ai summary for authorized buyers', function (): void {
	['company' => $company, 'user' => $user] = analyticsReportContext('owner');

	$expectedFilters = [
		'start_date' => '2025-01-01',
		'end_date' => '2025-01-31',
		'part_ids' => [101, 102],
		'category_ids' => ['machining', 'casting'],
		'location_ids' => [7],
	];

	$reportPayload = [
		'series' => [
			[
				'part_id' => 101,
				'part_name' => 'HX-101',
				'data' => [
					['date' => '2025-01-05', 'actual' => 14, 'forecast' => 12],
				],
			],
		],
		'table' => [
			[
				'part_id' => 101,
				'part_name' => 'HX-101',
				'total_forecast' => 120,
				'total_actual' => 140,
				'mape' => 0.08,
				'mae' => 6.4,
				'reorder_point' => 52,
				'safety_stock' => 18,
			],
		],
		'aggregates' => [
			'total_forecast' => 120,
			'total_actual' => 140,
			'mape' => 0.08,
			'mae' => 6.4,
			'avg_daily_demand' => 4.5,
			'recommended_reorder_point' => 56,
			'recommended_safety_stock' => 22,
		],
		'filters_used' => $expectedFilters,
	];

	$service = M::mock(AnalyticsService::class);
	$service->shouldReceive('generateForecastReport')
		->once()
		->with($company->id, $expectedFilters)
		->andReturn($reportPayload);
	app()->instance(AnalyticsService::class, $service);

	$aiResponse = [
		'summary_markdown' => 'AI summary from model.',
		'bullets' => ['Variance improved by 4 % QoQ.'],
		'provider' => 'llm',
		'source' => 'ai',
	];

	$aiClient = M::mock(AiClient::class);
	$aiClient->shouldReceive('summarizeReport')
		->once()
		->with(M::on(function (array $payload) use ($reportPayload): bool {
			expect($payload['report_type'])->toBe('forecast');
			expect($payload['report_data'])->toBe([
				'series' => $reportPayload['series'],
				'table' => $reportPayload['table'],
				'aggregates' => $reportPayload['aggregates'],
			]);

			return true;
		}))
		->andReturn([
			'status' => 'success',
			'message' => 'ok',
			'data' => $aiResponse,
		]);
	app()->instance(AiClient::class, $aiClient);

	actingAs($user);

	$query = analyticsQueryString([
		'start_date' => '2025-01-01',
		'end_date' => '2025-01-31',
		'part_ids' => ['101', '102'],
		'category_ids' => ['machining', 'casting'],
		'location_ids' => ['7'],
	]);

	$response = $this->postJson("/api/v1/analytics/forecast-report?{$query}");

	$response->assertOk()
		->assertJsonPath('message', 'Forecast report generated.')
		->assertJsonPath('data.report.series.0.part_id', 101)
		->assertJsonPath('data.report.filters_used', $expectedFilters)
		->assertJsonPath('data.summary.summary_markdown', 'AI summary from model.')
		->assertJsonPath('data.summary.provider', 'llm');
});

test('forecast report endpoint requires dedicated permission even when analytics access is enabled', function (): void {
	seedRoleTemplatePermissions('limited_analytics', ['analytics.read']);

	['company' => $company, 'user' => $user] = analyticsReportContext('limited_analytics');

	$service = M::mock(AnalyticsService::class);
	$service->shouldNotReceive('generateForecastReport');
	app()->instance(AnalyticsService::class, $service);

	app()->instance(AiClient::class, M::mock(AiClient::class));

	actingAs($user);

	$response = $this->postJson('/api/v1/analytics/forecast-report');

	$response->assertForbidden()
		->assertJsonPath('message', 'Access denied.')
		->assertJsonPath('errors.code', 'forecast_permission_required');
});

test('supplier performance endpoint returns report and ai summary for authorized buyer', function (): void {
	['company' => $company, 'user' => $user] = analyticsReportContext('owner');

	$supplier = Supplier::factory()->create(['company_id' => $company->id]);

	$expectedFilters = [
		'start_date' => '2025-02-01',
		'end_date' => '2025-02-28',
	];

	$reportPayload = [
		'series' => [
			[
				'metric_name' => 'on_time_delivery_rate',
				'data' => [
					['date' => '2025-02-07', 'value' => 0.94],
				],
			],
		],
		'table' => [
			[
				'supplier_id' => $supplier->id,
				'on_time_delivery_rate' => 0.94,
				'defect_rate' => 0.02,
				'lead_time_variance' => 1.8,
				'price_volatility' => 0.04,
				'service_responsiveness' => 6.5,
				'risk_category' => 'medium',
			],
		],
		'aggregates' => [
			'on_time_delivery_rate' => 0.94,
			'defect_rate' => 0.02,
			'lead_time_variance' => 1.8,
			'price_volatility' => 0.04,
			'service_responsiveness' => 6.5,
		],
		'filters_used' => $expectedFilters,
	];

	$service = M::mock(AnalyticsService::class);
	$service->shouldReceive('generateSupplierPerformanceReport')
		->once()
		->with($company->id, $supplier->id, $expectedFilters)
		->andReturn($reportPayload);
	app()->instance(AnalyticsService::class, $service);

	$aiClient = M::mock(AiClient::class);
	$aiClient->shouldReceive('summarizeReport')
		->once()
		->with(M::on(function (array $payload) use ($reportPayload): bool {
			expect($payload['report_type'])->toBe('supplier_performance');
			expect($payload['report_data']['aggregates']['on_time_delivery_rate'])->toBe(0.94);

			return true;
		}))
		->andReturn([
			'status' => 'success',
			'message' => 'ok',
			'data' => [
				'summary_markdown' => 'Supplier maintained 94 % on-time performance.',
				'bullets' => ['Defect rate steady at 2 %.'],
				'provider' => 'llm',
				'source' => 'ai',
			],
		]);
	app()->instance(AiClient::class, $aiClient);

	actingAs($user);

	$query = analyticsQueryString([
		'supplier_id' => (string) $supplier->id,
		'start_date' => '2025-02-01',
		'end_date' => '2025-02-28',
	]);

	$response = $this->postJson("/api/v1/analytics/supplier-performance-report?{$query}");

	$response->assertOk()
		->assertJsonPath('message', 'Supplier performance report generated.')
		->assertJsonPath('data.report.table.0.supplier_id', $supplier->id)
		->assertJsonPath('data.summary.summary_markdown', 'Supplier maintained 94 % on-time performance.');
});

test('supplier personas cannot override supplier_id to access other suppliers', function (): void {
	seedRoleTemplatePermissions('supplier_admin', ['analytics.read', 'view_supplier_performance', 'summarize_reports']);

	['company' => $company, 'user' => $user] = analyticsReportContext('supplier_admin');

	$supplier = Supplier::factory()->create(['company_id' => $company->id]);

	SupplierContact::factory()->create([
		'company_id' => $company->id,
		'supplier_id' => $supplier->id,
		'user_id' => $user->id,
	]);

	$persona = ActivePersona::fromArray([
		'key' => 'supplier:ctx',
		'type' => ActivePersona::TYPE_SUPPLIER,
		'company_id' => $company->id,
		'role' => 'supplier_admin',
		'supplier_id' => $supplier->id,
		'supplier_company_id' => $company->id,
	]);

	expect($persona)->not->toBeNull();

	$this->withSession(['active_persona' => $persona->toArray()]);

	$expectedFilters = [
		'start_date' => '2025-03-01',
		'end_date' => '2025-03-31',
	];

	$reportPayload = [
		'series' => [],
		'table' => [
			[
				'supplier_id' => $supplier->id,
				'on_time_delivery_rate' => 0.9,
				'defect_rate' => 0.03,
				'lead_time_variance' => 2.1,
				'price_volatility' => 0.05,
				'service_responsiveness' => 8.4,
				'risk_category' => 'low',
			],
		],
		'aggregates' => [],
		'filters_used' => $expectedFilters,
	];

	$service = M::mock(AnalyticsService::class);
	$service->shouldReceive('generateSupplierPerformanceReport')
		->once()
		->with($company->id, $supplier->id, $expectedFilters)
		->andReturn($reportPayload);
	app()->instance(AnalyticsService::class, $service);

	$aiClient = M::mock(AiClient::class);
	$aiClient->shouldReceive('summarizeReport')
		->once()
		->andReturn([
			'status' => 'success',
			'message' => 'ok',
			'data' => [
				'summary_markdown' => 'Supplier summary.',
				'bullets' => ['Performance steady.'],
				'provider' => 'llm',
				'source' => 'ai',
			],
		]);
	app()->instance(AiClient::class, $aiClient);

	actingAs($user);

	$query = analyticsQueryString([
		'supplier_id' => (string) ($supplier->id + 1000),
		'start_date' => '2025-03-01',
		'end_date' => '2025-03-31',
	]);

	$response = $this->postJson("/api/v1/analytics/supplier-performance-report?{$query}");

	$response->assertOk()
		->assertJsonPath('data.report.table.0.supplier_id', $supplier->id);
});

test('supplier options endpoint returns company-scoped suppliers for buyers', function (): void {
	['company' => $company, 'user' => $user] = analyticsReportContext('owner');

	$suppliers = Supplier::factory()->count(3)->sequence(
		['company_id' => $company->id, 'name' => 'Alpha Manufacturing'],
		['company_id' => $company->id, 'name' => 'Beta Plastics'],
		['company_id' => Company::factory()->create()->id, 'name' => 'Gamma Outsider'],
	)->create();

	actingAs($user);

	$response = $this->getJson('/api/v1/analytics/supplier-options?q=Beta&per_page=2');

	$response->assertOk()
		->assertJsonPath('data.items.0.name', 'Beta Plastics')
		->assertJsonCount(1, 'data.items');

	// Ensure selected supplier is prepended even when outside search window.
	$responseWithSelected = $this->getJson('/api/v1/analytics/supplier-options?q=Z&selected_id='.$suppliers[0]->id);

	$responseWithSelected->assertOk()
		->assertJsonPath('data.items.0.id', $suppliers[0]->id)
		->assertJsonPath('data.items.0.name', 'Alpha Manufacturing');
});

test('supplier personas only see their assigned supplier in options', function (): void {
	seedRoleTemplatePermissions('supplier_admin', ['analytics.read', 'view_supplier_performance']);

	['company' => $company, 'user' => $user] = analyticsReportContext('supplier_admin');

	$supplier = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Persona Supplier']);

	$persona = ActivePersona::fromArray([
		'key' => 'sup:ctx',
		'type' => ActivePersona::TYPE_SUPPLIER,
		'company_id' => $company->id,
		'role' => 'supplier_admin',
		'supplier_id' => $supplier->id,
		'supplier_company_id' => $company->id,
	]);

	$this->withSession(['active_persona' => $persona?->toArray()]);

	actingAs($user);

	$response = $this->getJson('/api/v1/analytics/supplier-options');

	$response->assertOk()
		->assertJsonCount(1, 'data.items')
		->assertJsonPath('data.items.0.id', $supplier->id)
		->assertJsonPath('data.items.0.name', 'Persona Supplier');
});
