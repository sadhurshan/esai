<?php

use App\Events\NotificationDispatched;
use App\Mail\NotificationMail;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Models\CopilotPrompt;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\Fluent\AssertableJson;
use Mockery as M;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->forgetInstance(AiClient::class);
});

afterEach(function (): void {
    M::close();
    app()->forgetInstance(AiClient::class);
});

function analyticsEnabledCompany(): Company
{
    $plan = Plan::factory()->create([
        'code' => 'growth',
        'analytics_enabled' => true,
        'analytics_history_months' => 12,
    ]);

    return Company::factory()->create([
        'plan_code' => $plan->code,
        'status' => 'active',
    ]);
}

it('requires approval when copilot query spans multiple metrics', function (): void {
    Event::fake();
    Mail::fake();

    $company = analyticsEnabledCompany();

    $actor = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    AnalyticsSnapshot::factory()->create([
        'company_id' => $company->id,
        'type' => AnalyticsSnapshot::TYPE_CYCLE_TIME,
        'period_start' => Carbon::now()->startOfMonth(),
        'period_end' => Carbon::now()->endOfMonth(),
        'value' => 12.5,
    ]);

    AnalyticsSnapshot::factory()->create([
        'company_id' => $company->id,
        'type' => AnalyticsSnapshot::TYPE_SPEND,
        'period_start' => Carbon::now()->startOfMonth(),
        'period_end' => Carbon::now()->endOfMonth(),
        'value' => 48000,
    ]);

    actingAs($actor);

    $response = $this->postJson('/api/copilot/analytics', [
        'query' => 'Show cycle time and spend performance for last month',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('status', 'error');

    expect(CopilotPrompt::count())->toBe(0)
        ->and(Notification::count())->toBe(0);

    Event::assertNotDispatched(NotificationDispatched::class);
    Mail::assertNothingQueued();
});

it('returns analytics results, logs prompt history, and notifies admins', function (): void {
    Event::fake();
    Mail::fake();

    $company = analyticsEnabledCompany();

    $actor = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
        'email' => 'buyer@example.com',
    ]);

    $finance = User::factory()->for($company)->create([
        'role' => 'finance',
        'email' => 'finance@example.com',
    ]);

    $snapshot = AnalyticsSnapshot::factory()->create([
        'company_id' => $company->id,
        'type' => AnalyticsSnapshot::TYPE_CYCLE_TIME,
        'period_start' => Carbon::now()->startOfMonth(),
        'period_end' => Carbon::now()->endOfMonth(),
        'value' => 7.25,
        'meta' => ['trend' => 'improving'],
    ]);

    actingAs($actor);

    $response = $this->postJson('/api/copilot/analytics', [
        'query' => 'Show our cycle time performance',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Analytics copilot results.')
        ->assertJsonPath('meta.query', 'Show our cycle time performance')
    ->assertJsonPath('meta.metrics.0', AnalyticsSnapshot::TYPE_CYCLE_TIME)
    ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)->etc());

    $prompt = CopilotPrompt::first();

    expect($prompt)->not->toBeNull()
        ->and($prompt->company_id)->toBe($company->id)
        ->and($prompt->user_id)->toBe($actor->id)
        ->and($prompt->metrics)->toBe([AnalyticsSnapshot::TYPE_CYCLE_TIME])
        ->and($prompt->response[0]['type'])->toBe(AnalyticsSnapshot::TYPE_CYCLE_TIME)
        ->and($prompt->meta['approved'])->toBeFalse();

    $notifications = Notification::query()
        ->where('event_type', 'analytics_query')
        ->orderBy('user_id')
        ->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('user_id')->all())->toEqualCanonicalizing([$actor->id, $finance->id]);

    Event::assertDispatchedTimes(NotificationDispatched::class, 2);

    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($actor): bool {
        return $mail->notification->user_id === $actor->id;
    });

    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($finance): bool {
        return $mail->notification->user_id === $finance->id;
    });

    Mail::assertQueuedCount(2);

    $responseData = collect($response->json('data'));

    expect($responseData->first())
        ->toMatchArray([
            'type' => AnalyticsSnapshot::TYPE_CYCLE_TIME,
            'value' => (float) $snapshot->value,
        ]);
});

it('routes forecast spend queries through the ai client', function (): void {
    Event::fake();
    Mail::fake();

    try {
        Carbon::setTestNow(Carbon::parse('2024-05-01 00:00:00', 'UTC'));

        $company = analyticsEnabledCompany();

        $actor = User::factory()->for($company)->create([
            'role' => 'buyer_admin',
            'email' => 'owner@example.com',
        ]);

        User::factory()->for($company)->create([
            'role' => 'finance',
            'email' => 'finance@example.com',
        ]);

        actingAs($actor);

        $aiClient = M::mock(AiClient::class);
        $aiClient->shouldReceive('forecastSpendTool')
            ->once()
            ->withArgs(function (array $payload) use ($company, $actor): bool {
                expect($payload['company_id'])->toBe($company->id)
                    ->and($payload['user_id'])->toBe($actor->id)
                    ->and($payload['inputs']['category'])->toBe('Raw Materials')
                    ->and($payload['inputs']['projected_period_days'])->toBe(45)
                    ->and($payload['context'])->toHaveCount(1);

                return true;
            })
            ->andReturn([
                'status' => 'success',
                'message' => 'Forecast complete.',
                'data' => [
                    'summary' => 'Expect +12% spend next month.',
                    'payload' => [
                        'projected_total' => 125000.45,
                        'projected_period_days' => 45,
                    ],
                    'citations' => [
                        ['source' => 'analytics_snapshot', 'id' => 'spend-trend-2024'],
                    ],
                ],
            ]);

        app()->instance(AiClient::class, $aiClient);

        $response = $this->postJson('/api/copilot/analytics', [
            'query' => 'Forecast spend for raw materials next month',
            'context' => [
                ['type' => 'note', 'content' => 'Seasonal demand spike'],
            ],
            'inputs' => [
                'category' => 'Raw Materials',
                'past_period_days' => 90,
                'projected_period_days' => 45,
                'drivers' => ['Orders backlog', 'Supplier capacity'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.type', 'forecast_spend')
            ->assertJsonPath('data.0.meta.summary', 'Expect +12% spend next month.')
            ->assertJsonPath('meta.metrics.0', 'forecast_spend');

        $prompt = CopilotPrompt::first();

        expect($prompt)->not->toBeNull()
            ->and($prompt->metrics)->toBe(['forecast_spend'])
            ->and($prompt->response[0]['meta']['payload']['projected_total'])->toBe(125000.45);

        expect(Notification::count())->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
});
