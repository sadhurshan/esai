<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createCompanyOwnerContext(): array
{
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$company, $owner];
}

it('allows company owners to upload and list KYC documents', function (): void {
    config(['documents.disk' => 's3']);
    config(['filesystems.disks.s3.url' => 'https://files.test/storage']);
    Storage::fake('s3');

    [$company, $owner] = createCompanyOwnerContext();

    actingAs($owner);

    $response = $this->postJson("/api/companies/{$company->id}/documents", [
        'type' => 'registration',
        'document' => UploadedFile::fake()->create('kyc.pdf', 256, 'application/pdf'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.type', 'registration')
        ->assertJsonPath('data.filename', 'kyc.pdf')
        ->assertJsonPath('data.download_url', fn ($url) => is_string($url) && $url !== '');

    $companyDocument = CompanyDocument::query()->first();

    expect($companyDocument)
        ->not->toBeNull()
        ->and($companyDocument->company_id)->toBe($company->id)
        ->and($companyDocument->document_id)->not->toBeNull();

    $document = Document::query()->find($companyDocument->document_id);

    expect($document)
        ->not->toBeNull()
        ->and($document->documentable_type)->toBe($company->getMorphClass())
        ->and($document->documentable_id)->toBe($company->id)
        ->and($document->kind)->toBe('supplier');

    Storage::disk('s3')->assertExists($companyDocument->path);

    $indexResponse = $this->getJson("/api/companies/{$company->id}/documents");

    $indexResponse
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonStructure([
            'status',
            'data' => [
                'items' => [
                    [
                        'id',
                        'company_id',
                        'document_id',
                        'type',
                        'filename',
                        'mime',
                        'size_bytes',
                        'download_url',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['per_page', 'next_cursor', 'prev_cursor'],
            ],
            'meta' => ['cursor' => ['next_cursor', 'prev_cursor', 'has_next', 'has_prev']],
        ]);
});

it('deletes binaries and linked documents when removing company KYC docs', function (): void {
    config(['documents.disk' => 's3']);
    config(['filesystems.disks.s3.url' => 'https://files.test/storage']);
    Storage::fake('s3');

    [$company, $owner] = createCompanyOwnerContext();

    actingAs($owner);

    $uploadResponse = $this->postJson("/api/companies/{$company->id}/documents", [
        'type' => 'tax',
        'document' => UploadedFile::fake()->create('tax-cert.pdf', 128, 'application/pdf'),
    ]);

    $uploadResponse->assertCreated();

    $companyDocument = CompanyDocument::query()->first();
    $path = $companyDocument->path;
    $documentId = $companyDocument->document_id;

    $deleteResponse = $this->deleteJson("/api/companies/{$company->id}/documents/{$companyDocument->id}");

    $deleteResponse->assertOk()
        ->assertJsonPath('message', 'Document removed.');

    $this->assertSoftDeleted('company_documents', ['id' => $companyDocument->id]);

    $linked = Document::withTrashed()->find($documentId);
    expect($linked)
        ->not->toBeNull()
        ->and($linked->trashed())
        ->toBeTrue();

    Storage::disk('s3')->assertMissing($path);
});

it('blocks unauthorized members from uploading KYC documents', function (): void {
    config(['documents.disk' => 's3']);
    Storage::fake('s3');

    [$company, $owner] = createCompanyOwnerContext();

    $requester = User::factory()->create([
        'role' => 'buyer_requester',
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $requester->id,
        'role' => $requester->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($requester);

    $response = $this->postJson("/api/companies/{$company->id}/documents", [
        'type' => 'registration',
        'document' => UploadedFile::fake()->create('blocked.pdf', 64, 'application/pdf'),
    ]);

    $response->assertForbidden();

    expect(CompanyDocument::query()->count())->toBe(0);
});
