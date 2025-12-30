<?php

use App\Models\AiChatThread;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use App\Services\Ai\WorkspaceToolResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

foreach (loadCopilotEvalCases() as $case) {
    test(sprintf('copilot eval case: %s', $case['id'] ?? 'case'), function () use ($case): void {
        runCopilotEvalCase($case);
    });
}

function runCopilotEvalCase(array $case): void
{
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => sprintf('Eval %s', $case['id'] ?? 'case'),
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = \Mockery::mock(AiClient::class);
    $resolver = \Mockery::mock(WorkspaceToolResolver::class);

    $intentPayload = [
        'status' => 'success',
        'message' => 'intent ready',
        'data' => $case['intent'],
    ];

    $client->shouldReceive('intentPlan')
        ->once()
        ->andReturn($intentPayload);

    if (isset($case['plan_action'])) {
        $client->shouldReceive('planAction')
            ->once()
            ->andReturn([
                'status' => 'success',
                'message' => 'draft ready',
                'data' => $case['plan_action'],
            ]);
    } else {
        $client->shouldNotReceive('planAction');
    }

    if (isset($case['help_response'])) {
        $client->shouldReceive('helpTool')
            ->once()
            ->andReturn([
                'status' => 'success',
                'message' => 'help ready',
                'data' => $case['help_response'],
            ]);
    } else {
        $client->shouldNotReceive('helpTool');
    }

    if (isset($case['review_call'])) {
        $method = $case['review_call']['method'];

        $client->shouldReceive($method)
            ->once()
            ->andReturn([
                'status' => 'success',
                'message' => 'review ready',
                'data' => $case['review_call']['data'],
            ]);
    }

    if (isset($case['chat'])) {
        $chatPayload = [
            'status' => 'success',
            'message' => 'continued',
            'data' => [
                'response' => $case['chat'],
                'memory' => [],
            ],
            'errors' => [],
        ];

        $client->shouldReceive('chatContinue')
            ->once()
            ->andReturn($chatPayload);
    } else {
        $client->shouldNotReceive('chatContinue');
    }

    $client->shouldNotReceive('chatRespond');

    app()->instance(AiClient::class, $client);

    if (isset($case['workspace_tools'])) {
        $toolMap = [];

        foreach ($case['workspace_tools'] as $entry) {
            $toolName = $entry['tool_name'] ?? null;

            if (is_string($toolName)) {
                $toolMap[$toolName] = $entry['result'] ?? null;
            }
        }

        $resolver->shouldReceive('resolveBatch')
            ->once()
            ->andReturnUsing(function (int $companyId, array $calls) use ($toolMap, $company): array {
                expect($companyId)->toBe($company->id);

                $results = [];

                foreach ($calls as $call) {
                    $toolName = (string) ($call['tool_name'] ?? '');
                    $results[] = [
                        'tool_name' => $toolName,
                        'call_id' => $call['call_id'] ?? (string) Str::uuid(),
                        'result' => $toolMap[$toolName] ?? null,
                    ];
                }

                return $results;
            });
    } else {
        $resolver->shouldNotReceive('resolveBatch');
    }

    app()->instance(WorkspaceToolResolver::class, $resolver);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $result = $chatService->sendMessage(
        $thread->fresh(),
        $user->fresh(),
        $case['prompt'],
    );

    expectEvalExpectation($result, $case['expect']);
}

/**
 * @param array<string, mixed> $result
 * @param array<string, mixed> $expectation
 */
function expectEvalExpectation(array $result, array $expectation): void
{
    expect(data_get($result, 'response.type'))->toBe($expectation['type']);

    if (isset($expectation['action_type'])) {
        expect(data_get($result, 'response.draft.action_type'))->toBe($expectation['action_type']);
    }

    $assertions = $expectation['assertions'] ?? [];

    foreach ($assertions as $assertion) {
        $path = $assertion['path'] ?? null;
        $needle = $assertion['contains'] ?? null;

        if (! is_string($path) || ! is_string($needle)) {
            continue;
        }

        $value = data_get($result, $path);
        $valueString = normalizeEvalValue($value);

        expect($valueString)->toContain($needle);
    }
}

/**
 * @return string
 */
function normalizeEvalValue(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '';
}

/**
 * @return list<array<string, mixed>>
 */
function loadCopilotEvalCases(): array
{
    static $cases = null;

    if (is_array($cases)) {
        return $cases;
    }

    $path = copilotEvalDatasetPath();

    if (! file_exists($path)) {
        throw new \RuntimeException('Evaluation dataset missing.');
    }

    $json = file_get_contents($path);

    if ($json === false) {
        throw new \RuntimeException('Unable to read evaluation dataset.');
    }

    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new \RuntimeException('Evaluation dataset must decode to an array.');
    }

    $normalized = [];

    foreach ($decoded as $index => $case) {
        if (! is_array($case)) {
            continue;
        }

        if (! isset($case['id']) || ! is_string($case['id'])) {
            $case['id'] = sprintf('case_%d', $index + 1);
        }

        $normalized[] = $case;
    }

    if ($normalized === []) {
        throw new \RuntimeException('Evaluation dataset is empty.');
    }

    $cases = $normalized;

    return $cases;
}

function copilotEvalDatasetPath(): string
{
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai_eval_cases.json';
}
