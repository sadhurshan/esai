<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_truncates_long_payload_fields(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $recorder = app(AiEventRecorder::class);
        $long = str_repeat('A', 15000);

        $event = $recorder->record(
            companyId: $company->id,
            userId: $user->id,
            feature: 'test_feature',
            requestPayload: [
                'long_value' => $long,
                'nested' => ['deep' => $long],
            ],
            responsePayload: [
                'answer' => $long,
            ],
        );

        $this->assertSame(10000, strlen($event->request_json['long_value']));
        $this->assertSame(10000, strlen($event->request_json['nested']['deep']));
        $this->assertSame(10000, strlen($event->response_json['answer']));
    }

    public function test_it_preserves_short_payloads(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $recorder = app(AiEventRecorder::class);
        $payload = [
            'query_preview' => 'short text',
            'filters' => ['source_type' => 'doc'],
        ];

        $event = $recorder->record(
            companyId: $company->id,
            userId: $user->id,
            feature: 'short_payload',
            requestPayload: $payload,
            responsePayload: $payload,
        );

        $this->assertSame($payload, $event->request_json);
        $this->assertSame($payload, $event->response_json);
    }
}
