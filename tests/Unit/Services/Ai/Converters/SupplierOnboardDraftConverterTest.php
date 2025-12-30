<?php

use App\Models\AiActionDraft;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierDocumentTask;
use App\Models\User;
use App\Services\Ai\Converters\SupplierOnboardDraftConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates a pending supplier with document tasks from an approved draft', function (): void {
    Carbon::setTestNow('2025-01-05 12:00:00');

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $draft = AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_SUPPLIER_ONBOARD_DRAFT,
        'status' => AiActionDraft::STATUS_APPROVED,
        'output_json' => [
            'action_type' => 'supplier_onboard_draft',
            'payload' => [
                'legal_name' => 'Atlas Precision Co',
                'country' => 'us',
                'email' => 'kyc@atlas.test',
                'phone' => '+1-555-123-4567',
                'payment_terms' => 'Net 45',
                'tax_id' => 'VAT-8821',
                'website' => 'https://atlas.test',
                'address' => '24 Harbor Way, Austin, TX',
                'notes' => 'Supplier needs NDA before sharing drawings.',
                'documents_needed' => [
                    [
                        'type' => 'iso9001',
                        'description' => 'Quality certification for aerospace parts.',
                        'required' => true,
                        'priority' => 1,
                        'due_in_days' => 30,
                        'notes' => 'Upload latest certificate.',
                    ],
                    [
                        'type' => 'insurance',
                        'description' => 'Proof of liability coverage.',
                        'required' => false,
                        'priority' => 3,
                        'due_in_days' => 45,
                        'notes' => null,
                    ],
                ],
            ],
            'citations' => [],
        ],
    ]);

    $converter = app(SupplierOnboardDraftConverter::class);

    $result = $converter->convert($draft, $user);

    /** @var Supplier $supplier */
    $supplier = $result['entity']->fresh(['documentTasks']);

    expect($supplier->company_id)->toBe($company->id)
        ->and($supplier->status)->toBe('pending')
        ->and($supplier->payment_terms)->toBe('Net 45')
        ->and($supplier->tax_id)->toBe('VAT-8821')
        ->and($supplier->email)->toBe('kyc@atlas.test');

    $tasks = SupplierDocumentTask::query()->forCompany($company->id)->orderBy('document_type')->get();

    expect($tasks)->toHaveCount(2);

    $isoTask = $tasks->firstWhere('document_type', 'iso9001');
    $insuranceTask = $tasks->firstWhere('document_type', 'insurance');

    expect($isoTask)->not->toBeNull()
        ->and($isoTask?->is_required)->toBeTrue()
        ->and($isoTask?->priority)->toBe(1)
        ->and($isoTask?->due_at?->isSameDay(Carbon::now()->addDays(30)))->toBeTrue();

    expect($insuranceTask)->not->toBeNull()
        ->and($insuranceTask?->is_required)->toBeFalse()
        ->and($insuranceTask?->priority)->toBe(3)
        ->and($insuranceTask?->due_at?->isSameDay(Carbon::now()->addDays(45)))->toBeTrue();

    Carbon::setTestNow();
});

it('updates an existing supplier and refreshes document tasks when onboarding repeats', function (): void {
    Carbon::setTestNow('2025-03-01 09:00:00');

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $supplier = Supplier::factory()->for($company)->create([
        'name' => 'Beacon Metals',
        'email' => 'hello@beacon.test',
        'status' => 'approved',
        'payment_terms' => 'Net 30',
        'tax_id' => 'VAT-1111',
    ]);

    $existingTask = SupplierDocumentTask::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'document_type' => 'insurance',
        'status' => SupplierDocumentTask::STATUS_PENDING,
        'priority' => 2,
        'due_at' => Carbon::now()->addDays(3),
    ]);

    $draft = AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_SUPPLIER_ONBOARD_DRAFT,
        'status' => AiActionDraft::STATUS_APPROVED,
        'output_json' => [
            'action_type' => 'supplier_onboard_draft',
            'payload' => [
                'legal_name' => 'Beacon Metals',
                'country' => 'CA',
                'email' => 'hello@beacon.test',
                'phone' => '+1-555-900-2222',
                'payment_terms' => 'Net 60',
                'tax_id' => 'VAT-5555',
                'documents_needed' => [
                    [
                        'type' => 'insurance',
                        'required' => true,
                        'priority' => 1,
                        'due_in_days' => 10,
                        'description' => 'Renew coverage for 2025.',
                    ],
                    [
                        'type' => 'nda',
                        'required' => true,
                        'priority' => 2,
                        'due_in_days' => 5,
                    ],
                ],
            ],
        ],
    ]);

    $converter = app(SupplierOnboardDraftConverter::class);
    $converter->convert($draft, $user);

    $supplier->refresh();

    expect($supplier->status)->toBe('pending')
        ->and($supplier->payment_terms)->toBe('Net 60')
        ->and($supplier->tax_id)->toBe('VAT-5555');

    $tasks = SupplierDocumentTask::query()
        ->forCompany($company->id)
        ->where('supplier_id', $supplier->id)
        ->get();

    expect($tasks)->toHaveCount(2);

    $updatedInsurance = $tasks->firstWhere('document_type', 'insurance');
    $ndaTask = $tasks->firstWhere('document_type', 'nda');

    expect($updatedInsurance?->id)->toBe($existingTask->id)
        ->and($updatedInsurance?->priority)->toBe(1)
        ->and($updatedInsurance?->due_at?->isSameDay(Carbon::now()->addDays(10)))->toBeTrue();

    expect($ndaTask)->not->toBeNull()
        ->and($ndaTask?->due_at?->isSameDay(Carbon::now()->addDays(5)))->toBeTrue();

    Carbon::setTestNow();
});
