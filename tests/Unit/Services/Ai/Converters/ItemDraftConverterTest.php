<?php

use App\Models\AiActionDraft;
use App\Models\Company;
use App\Models\Part;
use App\Models\PartPreferredSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Converters\ItemDraftConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an inventory item and preferred suppliers from an approved draft', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $primarySupplier = Supplier::factory()->for($company)->create();
    $fallbackSupplier = Supplier::factory()->for($company)->create(['name' => 'Fallback Co']);

    $draft = AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_ITEM_DRAFT,
        'status' => AiActionDraft::STATUS_APPROVED,
        'input_json' => [
            'query' => 'Create item Rotar Blades',
            'inputs' => [],
            'entity_context' => null,
        ],
        'output_json' => [
            'action_type' => 'item_draft',
            'summary' => 'Item draft ready.',
            'payload' => [
                'item_code' => 'ROTAR-100',
                'name' => 'Rotar Blades',
                'uom' => 'EA',
                'status' => 'active',
                'category' => 'Blades',
                'description' => 'Replacement blades for the Rotar line.',
                'spec' => 'REV A',
                'attributes' => [
                    'material' => '440C',
                    'length_mm' => '150',
                ],
                'preferred_suppliers' => [
                    [
                        'supplier_id' => $primarySupplier->id,
                        'priority' => 1,
                        'notes' => 'Primary source',
                    ],
                    [
                        'name' => $fallbackSupplier->name,
                        'priority' => 3,
                        'notes' => 'Backup when lead time slips',
                    ],
                ],
            ],
            'citations' => [],
        ],
    ]);

    $converter = app(ItemDraftConverter::class);

    $result = $converter->convert($draft, $user);

    /** @var Part $part */
    $part = $result['entity']->fresh(['preferredSuppliers']);

    expect($part->part_number)->toBe('ROTAR-100')
        ->and($part->name)->toBe('Rotar Blades')
        ->and($part->uom)->toBe('EA')
        ->and($part->category)->toBe('Blades')
        ->and($part->spec)->toBe('REV A')
        ->and($part->attributes)->toMatchArray(['material' => '440C', 'length_mm' => '150'])
        ->and($part->active)->toBeTrue();

    $preferences = PartPreferredSupplier::query()
        ->forCompany($company->id)
        ->where('part_id', $part->id)
        ->orderBy('priority')
        ->get();

    expect($preferences)->toHaveCount(2)
        ->and($preferences->first()->supplier_id)->toBe($primarySupplier->id)
        ->and($preferences->first()->priority)->toBe(1)
        ->and($preferences->first()->notes)->toBe('Primary source')
        ->and($preferences->last()->supplier_id)->toBe($fallbackSupplier->id)
        ->and($preferences->last()->priority)->toBe(2)
        ->and($preferences->last()->notes)->toBe('Backup when lead time slips');
});

it('updates an existing item and clears legacy preferred suppliers', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $legacySupplier = Supplier::factory()->for($company)->create();

    $part = Part::factory()->for($company)->create([
        'part_number' => 'ROTAR-100',
        'name' => 'Rotar Blades',
        'uom' => 'EA',
        'spec' => 'OLD',
        'active' => true,
    ]);

    PartPreferredSupplier::factory()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'supplier_id' => $legacySupplier->id,
        'priority' => 1,
        'notes' => 'Legacy',
    ]);

    $draft = AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_ITEM_DRAFT,
        'status' => AiActionDraft::STATUS_APPROVED,
        'output_json' => [
            'action_type' => 'item_draft',
            'payload' => [
                'item_code' => 'ROTAR-100',
                'name' => 'Rotar Blades',
                'uom' => 'EA',
                'status' => 'inactive',
                'spec' => 'REV B',
                'preferred_suppliers' => [],
            ],
        ],
    ]);

    $converter = app(ItemDraftConverter::class);
    $converter->convert($draft, $user);

    $part->refresh();

    expect($part->spec)->toBe('REV B')
        ->and($part->active)->toBeFalse();

    expect(PartPreferredSupplier::query()->where('part_id', $part->id)->count())->toBe(0);
});
