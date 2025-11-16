<?php

use function Pest\Laravel\assertDatabaseHas;

it('returns numbering defaults for all document types', function (): void {
    createLocalizationFeatureUser();

    $response = $this->getJson('/api/settings/numbering');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => ['rfq', 'quote', 'po', 'invoice', 'grn', 'credit'],
        ])
        ->assertJsonPath('data.po.seq_len', 4)
        ->assertJsonPath('data.po.reset', 'never');
});

it('updates numbering rules for a document type', function (): void {
    $user = createLocalizationFeatureUser();

    $payload = [
        'po' => [
            'prefix' => 'PO-',
            'seq_len' => 5,
            'next' => 42,
            'reset' => 'yearly',
        ],
    ];

    $response = $this->patchJson('/api/settings/numbering', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.po.prefix', 'PO-')
        ->assertJsonPath('data.po.seq_len', 5)
        ->assertJsonPath('data.po.reset', 'yearly');

    assertDatabaseHas('company_document_numberings', [
        'company_id' => $user->company_id,
        'document_type' => 'po',
        'seq_len' => 5,
        'reset' => 'yearly',
    ]);
});
