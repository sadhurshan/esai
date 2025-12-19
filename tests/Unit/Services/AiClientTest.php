<?php

namespace Tests\Unit\Services;

use App\Models\AiEvent;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request as HttpRequest;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        config()->set('ai.base_url', 'http://ai.test');
        config()->set('ai.shared_secret', 'test-secret');
        config()->set('ai.timeout_seconds', 5);
        config()->set('ai.circuit_breaker.enabled', true);
        config()->set('ai.circuit_breaker.failure_threshold', 2);
        config()->set('ai.circuit_breaker.window_seconds', 60);
        config()->set('ai.circuit_breaker.open_seconds', 300);
    }

    public function test_circuit_opens_after_consecutive_failures(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        Http::fake(fn () => Http::response([
            'status' => 'error',
            'message' => 'Downstream failure',
        ], 500));

        $client = $this->makeClient();
        $payload = ['company_id' => $company->id];

        $client->forecast($payload);
        $client->forecast($payload);

        $response = $client->forecast($payload);

        $this->assertSame('error', $response['status']);
        $this->assertSame('AI temporarily unavailable. Please retry shortly.', $response['message']);
        $this->assertNull($response['data']);
        $this->assertArrayHasKey('service', $response['errors']);

        Http::assertSentCount(2);

        $events = AiEvent::query()->where('feature', 'ai_circuit_breaker')->get();
        $this->assertCount(2, $events);
        $this->assertContains('circuit_open', $events->pluck('request_json.action')->all());
        $this->assertContains('circuit_skip', $events->pluck('request_json.action')->all());
    }

    public function test_successful_call_resets_failure_window(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        Http::fakeSequence()
            ->push(['status' => 'error', 'message' => 'fail'], 500)
            ->push(['status' => 'success', 'message' => 'ok', 'data' => ['value' => 1]], 200)
            ->push(['status' => 'error', 'message' => 'fail'], 500)
            ->push(['status' => 'error', 'message' => 'fail'], 500);

        $client = $this->makeClient();
        $payload = ['company_id' => $company->id];

        $client->forecast($payload);
        $client->forecast($payload);
        $client->forecast($payload);

        $response = $client->forecast($payload);
        $this->assertSame('error', $response['status']);

        $shortCircuit = $client->forecast($payload);
        $this->assertSame('AI temporarily unavailable. Please retry shortly.', $shortCircuit['message']);

        Http::assertSentCount(4);

        $events = AiEvent::query()->where('feature', 'ai_circuit_breaker')->get();
        $this->assertCount(2, $events);
        $this->assertEqualsCanonicalizing(['circuit_open', 'circuit_skip'], $events->pluck('request_json.action')->all());
    }

    public function test_document_index_search_and_answer_methods_delegate_to_microservice(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        Http::fake([
            'ai.test/index/document' => Http::response(['status' => 'ok', 'data' => ['indexed_chunks' => 3], 'message' => ''], 200),
            'ai.test/search' => Http::response(['status' => 'ok', 'data' => ['hits' => []], 'message' => ''], 200),
            'ai.test/answer' => Http::response(['status' => 'ok', 'data' => ['answer' => ''], 'message' => ''], 200),
        ]);

        $client = $this->makeClient();
        $payload = ['company_id' => $company->id];

        $indexResponse = $client->indexDocument($payload);
        $searchResponse = $client->search($payload);
        $answerResponse = $client->answer($payload);

        $this->assertSame('success', $indexResponse['status']);
        $this->assertSame('success', $searchResponse['status']);
        $this->assertSame('success', $answerResponse['status']);

        Http::assertSent(function ($request) {
            $endpoint = $request->url();

            return str_contains($endpoint, 'index/document');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/answer');
        });
    }

    public function test_payload_includes_audit_context_and_safety_identifier(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'email' => 'auditor@example.com',
            'role' => 'buyer_admin',
        ]);

        CompanyContext::set($company->id);
        $this->actingAs($user);
        $request = request();
        $request->setUserResolver(fn () => $user);
        config()->set('app.key', 'unit-test-key');

        $capturedPayload = null;

        Http::fake([
            'ai.test/search' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'status' => 'success',
                    'message' => 'ok',
                    'data' => ['hits' => []],
                ], 200);
            },
        ]);

        $client = $this->makeClient($request);
        $client->search([
            'company_id' => $company->id,
            'query' => 'Provide audit trails',
            'top_k' => 3,
        ]);

        $this->assertIsArray($capturedPayload);
        $this->assertSame($company->id, $capturedPayload['company_id']);
        $this->assertSame($company->id, $capturedPayload['audit_context']['company_id']);
        $this->assertSame($user->id, $capturedPayload['audit_context']['user_id']);
        $this->assertSame($user->role, $capturedPayload['audit_context']['role']);

        $expectedSafetyId = hash('sha256', sprintf('%s|%s', config('app.key'), $user->email));
        $this->assertSame($expectedSafetyId, $capturedPayload['safety_identifier']);
    }

    public function test_answer_payload_includes_openai_provider_when_enabled(): void
    {
        $company = Company::factory()->create();
        CompanyAiSetting::factory()->for($company)->create([
            'llm_answers_enabled' => true,
            'llm_provider' => 'openai',
        ]);

        CompanyContext::set($company->id);

        $capturedPayload = null;
        Http::fake([
            'ai.test/answer' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'status' => 'success',
                    'message' => 'ok',
                    'data' => ['answer_markdown' => ''],
                ], 200);
            },
        ]);

        $client = $this->makeClient();
        $client->answer([
            'company_id' => $company->id,
            'query' => 'Summarize',
        ]);

        $this->assertIsArray($capturedPayload);
        $this->assertSame('openai', $capturedPayload['llm_provider']);
        $this->assertTrue($capturedPayload['llm_answers_enabled']);
    }

    public function test_answer_payload_defaults_to_dummy_provider_when_disabled(): void
    {
        $company = Company::factory()->create();
        CompanyAiSetting::factory()->for($company)->create([
            'llm_answers_enabled' => false,
            'llm_provider' => 'dummy',
        ]);

        CompanyContext::set($company->id);

        $capturedPayload = null;
        Http::fake([
            'ai.test/answer' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'status' => 'success',
                    'message' => 'ok',
                    'data' => ['answer_markdown' => ''],
                ], 200);
            },
        ]);

        $client = $this->makeClient();
        $client->answer([
            'company_id' => $company->id,
            'query' => 'Summarize',
        ]);

        $this->assertIsArray($capturedPayload);
        $this->assertSame('dummy', $capturedPayload['llm_provider']);
        $this->assertFalse($capturedPayload['llm_answers_enabled']);
    }

    private function makeClient(?HttpRequest $request = null): AiClient
    {
        return new AiClient(
            http: app(HttpFactory::class),
            cache: new CacheRepository(new ArrayStore()),
            recorder: app(AiEventRecorder::class),
            request: $request,
        );
    }
}
