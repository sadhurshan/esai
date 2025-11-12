<?php

use App\Enums\RateLimitScope;
use App\Models\Company;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RateLimit;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (! Route::has('contract.rate-limited')) {
        Route::middleware(['api', 'rate.limit.enforcer:api'])
            ->get('/contract/rate-limited', static fn () => response()->json(['status' => 'ok']))
            ->name('contract.rate-limited');
    }
});

it('returns the error envelope for validation failures', function (): void {
    $plan = Plan::factory()->create([
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 25,
        'storage_gb' => 10,
    ]);

    $company = Company::factory()->for($plan)->create([
        'plan_code' => $plan->code,
    ]);

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    $supplier = User::factory()->for($company)->create([
        'role' => 'supplier_admin',
    ]);

    $rfq = RFQ::factory()->for($company)->create();

    actingAs($supplier);

    $response = postJson("/api/rfqs/{$rfq->id}/clarifications/question", []);

    $response
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('data', null)
        ->assertJsonStructure(['status', 'message', 'data', 'errors']);
});

it('returns the error envelope for forbidden actions', function (): void {
    $user = createLocalizationFeatureUser([], [], 'buyer_member');

    $response = postJson('/api/localization/uoms', [
        'name' => 'Test UOM',
        'symbol' => 'TU',
        'precision' => 2,
        'type' => 'custom',
    ]);

    $response
        ->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('data', null)
        ->assertJsonStructure(['status', 'message', 'data']);
});

it('returns the error envelope when rate limits are exceeded', function (): void {
    RateLimit::factory()->create([
        'company_id' => null,
        'window_seconds' => 60,
        'max_requests' => 0,
        'scope' => RateLimitScope::Api,
    ]);

    $response = getJson('/contract/rate-limited');

    $response
        ->assertStatus(429)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('data', null)
        ->assertJsonStructure(['status', 'message', 'data']);
});
