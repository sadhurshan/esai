<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows authenticated users to register their company', function () {
    $user = User::factory()->create([
        'company_id' => null,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $payload = [
        'name' => 'Orbital Precision Manufacturing',
        'registration_no' => 'US-987654321',
        'tax_id' => '55-1234567',
        'country' => 'US',
        'email_domain' => 'orbital-example.com',
        'primary_contact_name' => 'Morgan Pratt',
        'primary_contact_email' => 'morgan@orbital-example.com',
        'primary_contact_phone' => '+1-555-123-4567',
        'address' => '1200 Aviation Blvd, Seattle, WA',
        'phone' => '+1-555-000-2222',
        'website' => 'https://orbital-example.com',
        'region' => 'North America',
    ];

    $response = $this->postJson('/api/companies', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', $payload['name'])
        ->assertJsonPath('data.status', CompanyStatus::Pending->value);

    $company = Company::where('name', $payload['name'])->first();

    expect($company)->not->toBeNull()
        ->and($company->status)->toBe(CompanyStatus::Pending)
        ->and($company->owner_user_id)->toBe($user->id)
        ->and($user->fresh()->company_id)->toBe($company?->id);
});

it('rejects company registration for guests', function () {
    $payload = [
        'name' => 'Unlinked Manufacturing',
        'registration_no' => 'US-111222333',
        'tax_id' => '44-9876543',
        'country' => 'US',
        'email_domain' => 'unlinked.com',
        'primary_contact_name' => 'Taylor Lane',
        'primary_contact_email' => 'taylor@unlinked.com',
        'primary_contact_phone' => '+1-555-111-9999',
    ];

    $response = $this->postJson('/api/companies', $payload);

    $response->assertStatus(401)
        ->assertJsonPath('status', 'error');
});

it('allows company owners to update profile details', function () {
    $user = User::factory()->create([
        'role' => 'buyer_admin',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending,
        'owner_user_id' => $user->id,
        'email_domain' => 'initial-domain.com',
    ]);

    $user->forceFill(['company_id' => $company->id])->save();

    actingAs($user);

    $response = $this->putJson("/api/companies/{$company->id}", [
        'name' => 'Updated Manufacturing Group',
        'email_domain' => 'updated-domain.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Updated Manufacturing Group');

    expect($company->refresh()->email_domain)->toBe('updated-domain.com');
});

it('prevents users from updating other companies', function () {
    $owner = User::factory()->create(['role' => 'buyer_admin']);
    $otherUser = User::factory()->create(['role' => 'buyer_admin']);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending,
        'owner_user_id' => $owner->id,
    ]);

    $otherCompany = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'owner_user_id' => $otherUser->id,
    ]);

    $otherUser->forceFill(['company_id' => $otherCompany->id])->save();

    actingAs($otherUser);

    $response = $this->putJson("/api/companies/{$company->id}", [
        'name' => 'Unauthorized Update Attempt',
    ]);

    $response->assertStatus(403);
});

it('stores company documents and emits audit log entries', function () {
    Storage::fake('local');

    $user = User::factory()->create(['role' => 'buyer_admin']);
    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending,
        'owner_user_id' => $user->id,
    ]);

    $user->forceFill(['company_id' => $company->id])->save();

    actingAs($user);

    $file = UploadedFile::fake()->create('registration.pdf', 200, 'application/pdf');

    $response = $this->post('/api/companies/'.$company->id.'/documents', [
        'type' => 'registration',
        'document' => $file,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.type', 'registration');

    $stored = $company->documents()->first();

    expect($stored)->not->toBeNull();
    Storage::disk(config('filesystems.default'))->assertExists($stored?->path);
});

it('allows company owners to delete previously uploaded documents', function () {
    Storage::fake('local');

    $user = User::factory()->create(['role' => 'buyer_admin']);
    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending,
        'owner_user_id' => $user->id,
    ]);

    $user->forceFill(['company_id' => $company->id])->save();

    actingAs($user);

    $upload = UploadedFile::fake()->create('registration.pdf', 200, 'application/pdf');

    $createResponse = $this->post('/api/companies/'.$company->id.'/documents', [
        'type' => 'registration',
        'document' => $upload,
    ]);

    $documentId = $createResponse->json('data.id');

    $deleteResponse = $this->deleteJson("/api/companies/{$company->id}/documents/{$documentId}");

    $deleteResponse->assertOk()
        ->assertJsonPath('status', 'success');

    expect($company->refresh()->documents()->count())->toBe(0);
});

it('allows platform super admins to list pending companies', function () {
    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    Company::factory()->count(2)->create([
        'status' => CompanyStatus::Pending,
    ]);

    actingAs($admin);

    $response = $this->getJson('/api/admin/companies?status=pending');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'items',
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ],
        ]);
});

it('forbids non-platform users from accessing the admin company list', function () {
    $user = User::factory()->create(['role' => 'buyer_admin']);
    actingAs($user);

    $response = $this->getJson('/api/admin/companies');

    $response->assertStatus(403);
});

it('approves pending companies when performed by a super admin', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);
    $company = Company::factory()->create(['status' => CompanyStatus::Pending]);

    actingAs($admin);

    $response = $this->postJson("/api/admin/companies/{$company->id}/approve");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', CompanyStatus::Active->value);

    expect($company->refresh()->status)->toBe(CompanyStatus::Active);
});

it('rejects pending companies with a reason when performed by a super admin', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);
    $company = Company::factory()->create(['status' => CompanyStatus::Pending]);

    actingAs($admin);

    $response = $this->postJson("/api/admin/companies/{$company->id}/reject", [
        'reason' => 'Missing tax documentation',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', CompanyStatus::Rejected->value)
        ->assertJsonPath('data.rejection_reason', 'Missing tax documentation');

    expect($company->refresh()->status)->toBe(CompanyStatus::Rejected)
        ->and($company->rejection_reason)->toBe('Missing tax documentation');
});
