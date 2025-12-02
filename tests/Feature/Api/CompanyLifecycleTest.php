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
    Company::factory()->create([
        'name' => 'Orbital Precision Manufacturing',
        'slug' => 'orbital-precision-manufacturing-legacy',
        'email_domain' => 'orbital-example.com',
    ]);

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
        ->assertJsonPath('data.status', CompanyStatus::PendingVerification->value)
        ->assertJsonPath('data.has_completed_onboarding', true);

    $company = Company::where('owner_user_id', $user->id)->first();

    expect($company)->not->toBeNull()
    ->and($company->status)->toBe(CompanyStatus::PendingVerification)
        ->and($company->owner_user_id)->toBe($user->id)
        ->and($user->fresh()->company_id)->toBe($company?->id)
        ->and(Company::where('name', $payload['name'])->count())->toBe(2);
});

it('updates the existing placeholder company when onboarding details are submitted', function () {
    $user = User::factory()->create([
        'role' => 'buyer_admin',
    ]);

    $placeholder = Company::factory()->create([
        'owner_user_id' => $user->id,
        'registration_no' => null,
        'tax_id' => null,
        'country' => null,
        'email_domain' => null,
        'primary_contact_name' => null,
        'primary_contact_email' => null,
        'primary_contact_phone' => null,
    ]);

    $user->forceFill(['company_id' => $placeholder->id])->save();

    actingAs($user);

    $payload = [
        'name' => 'Vanguard Robotics Inc.',
        'registration_no' => 'EU-445566',
        'tax_id' => '88-5566778',
        'country' => 'DE',
        'email_domain' => 'vanguard-robotics.com',
        'primary_contact_name' => 'Elena Fischer',
        'primary_contact_email' => 'elena@vanguard-robotics.com',
        'primary_contact_phone' => '+49-30-1234567',
        'address' => 'Innovation Park 5, Berlin',
        'phone' => '+49-30-7654321',
        'website' => 'https://vanguard-robotics.com',
        'region' => 'Europe',
    ];

    $response = $this->postJson('/api/companies', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $placeholder->id)
        ->assertJsonPath('data.name', $payload['name'])
        ->assertJsonPath('data.has_completed_onboarding', true);

    expect(Company::count())->toBe(1)
        ->and($placeholder->refresh()->registration_no)->toBe($payload['registration_no'])
        ->and($placeholder->country)->toBe($payload['country'])
        ->and($placeholder->email_domain)->toBe($payload['email_domain']);
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
                'meta' => ['next_cursor', 'prev_cursor', 'per_page'],
            ],
            'meta' => [
                'request_id',
                'cursor' => ['next_cursor', 'prev_cursor', 'has_next', 'has_prev'],
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
