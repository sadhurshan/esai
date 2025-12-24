<?php

use App\Models\Company;
use App\Services\Ai\AiClient;
use App\Services\Ai\WorkspaceToolResolver;

it('returns placeholder receipts payloads for workspace.get_receipts', function () {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_receipts',
        'call_id' => 'call-receipts',
        'arguments' => [
            'context' => ['origin' => 'spec-test'],
            'filters' => ['supplier_name' => 'Helios Industries'],
            'limit' => 2,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.get_receipts');

    $payload = $results[0]['result'];

    expect($payload)->toBeArray()
        ->and($payload['items'])->toHaveCount(2)
        ->and($payload['items'][0])->toHaveKeys([
            'id',
            'receipt_number',
            'supplier_name',
            'status',
            'total_amount',
            'created_at',
        ])
        ->and($payload['items'][0]['created_at'])->toBeString()
        ->and($payload['items'][0]['total_amount'])->toBeFloat();

    expect($payload['meta']['filters'])->toMatchArray(['supplier_name' => 'Helios Industries'])
        ->and($payload['meta']['context'])->toMatchArray(['origin' => 'spec-test']);
});

it('returns placeholder invoice payloads for workspace.get_invoices', function () {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_invoices',
        'call_id' => 'call-invoices',
        'arguments' => [
            'context' => ['origin' => 'spec-test'],
            'filters' => ['supplier_name' => 'Helios Industries'],
            'limit' => 3,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.get_invoices');

    $payload = $results[0]['result'];

    expect($payload)->toBeArray()
        ->and($payload['items'])->toHaveCount(3)
        ->and($payload['items'][0])->toHaveKeys([
            'id',
            'invoice_number',
            'supplier_name',
            'status',
            'total_amount',
            'created_at',
        ])
        ->and($payload['items'][0]['created_at'])->toBeString()
        ->and($payload['items'][0]['total_amount'])->toBeFloat();

    expect($payload['meta']['filters'])->toMatchArray(['supplier_name' => 'Helios Industries'])
        ->and($payload['meta']['context'])->toMatchArray(['origin' => 'spec-test']);
});

it('delegates workspace.help to the AI help tool', function (): void {
    $company = Company::factory()->create();

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('helpTool')
        ->once()
        ->with(\Mockery::on(function (array $payload) use ($company): bool {
            expect($payload['company_id'] ?? null)->toBe($company->id);
            expect($payload['inputs']['topic'] ?? null)->toBe('Approve invoice');

            return true;
        }))
        ->andReturn([
            'status' => 'success',
            'message' => 'Workspace help guide generated.',
            'data' => [
                'summary' => 'Guided steps ready.',
                'payload' => ['topic' => 'approve invoice'],
                'citations' => [['doc_id' => 'doc-1']],
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.help',
        'call_id' => 'call-help',
        'arguments' => [
            'topic' => 'Approve invoice',
            'context' => [['doc_id' => 'doc-1']],
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.help');

    $payload = $results[0]['result'];

    expect($payload)->toMatchArray([
        'summary' => 'Guided steps ready.',
        'payload' => ['topic' => 'approve invoice'],
        'citations' => [['doc_id' => 'doc-1']],
    ]);
});
