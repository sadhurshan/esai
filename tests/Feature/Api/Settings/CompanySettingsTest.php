<?php

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\assertDatabaseHas;

it('returns company profile defaults', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->getJson('/api/settings/company');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['legal_name', 'display_name', 'emails', 'phones']]);

    assertDatabaseHas('company_profiles', [
        'company_id' => $user->company_id,
        'legal_name' => $user->company->name,
    ]);
});

it('updates company profile details', function (): void {
    $user = createLocalizationFeatureUser();

    $payload = [
        'legal_name' => 'Elements Supply Holdings',
        'display_name' => 'Elements Supply',
        'tax_id' => '99-1234567',
        'registration_number' => 'REG-123',
        'emails' => ['ops@example.com'],
        'phones' => ['+1-555-1000'],
        'bill_to' => [
            'attention' => 'AP',
            'line1' => '1 Market St',
            'country' => 'US',
        ],
        'ship_from' => [
            'line1' => 'Warehouse Rd',
            'country' => 'US',
        ],
    ];

    $response = $this->patchJson('/api/settings/company', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Company settings updated.')
        ->assertJsonPath('data.legal_name', 'Elements Supply Holdings')
        ->assertJsonPath('data.bill_to.country', 'US');

    assertDatabaseHas('company_profiles', [
        'company_id' => $user->company_id,
        'legal_name' => 'Elements Supply Holdings',
        'tax_id' => '99-1234567',
    ]);
});

it('stores uploaded branding assets', function (): void {
    Storage::fake('public');

    $user = createLocalizationFeatureUser();

    $logo = UploadedFile::fake()->image('logo.png', 512, 512);
    $mark = UploadedFile::fake()->image('mark.png', 256, 256);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->patch('/api/settings/company', [
            'legal_name' => 'Elements Supply',
            'display_name' => 'Elements Supply',
            'logo' => $logo,
            'mark' => $mark,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.logo_url', fn ($value) => is_string($value) && str_contains($value, '/company-branding/'))
        ->assertJsonPath('data.mark_url', fn ($value) => is_string($value) && str_contains($value, '/company-branding/'));

    $profile = CompanyProfile::query()->where('company_id', $user->company_id)->firstOrFail();

    $logoPath = $profile->getRawOriginal('logo_url');
    $markPath = $profile->getRawOriginal('mark_url');

    expect($logoPath)->not->toBeNull();
    expect($markPath)->not->toBeNull();

    Storage::disk('public')->assertExists($logoPath);
    Storage::disk('public')->assertExists($markPath);
});

it('removes stored branding assets when cleared', function (): void {
    Storage::fake('public');

    $user = createLocalizationFeatureUser();

    $profile = CompanyProfile::query()->updateOrCreate(
        ['company_id' => $user->company_id],
        [
            'legal_name' => 'Elements Supply',
            'display_name' => 'Elements Supply',
            'logo_url' => sprintf('company-branding/%d/logo/original.png', $user->company_id),
            'mark_url' => sprintf('company-branding/%d/mark/original.png', $user->company_id),
        ]
    );

    $existingLogoPath = $profile->getRawOriginal('logo_url');
    $existingMarkPath = $profile->getRawOriginal('mark_url');

    Storage::disk('public')->put($existingLogoPath, 'logo-bytes');
    Storage::disk('public')->put($existingMarkPath, 'mark-bytes');

    $this
        ->withHeader('Accept', 'application/json')
        ->patch('/api/settings/company', [
            'legal_name' => 'Elements Supply',
            'display_name' => 'Elements Supply',
            'logo_url' => '',
            'mark_url' => '',
        ])
        ->assertOk()
        ->assertJsonPath('data.logo_url', null)
        ->assertJsonPath('data.mark_url', null);

    $profile->refresh();

    expect($profile->logo_url)->toBeNull();
    expect($profile->mark_url)->toBeNull();
    Storage::disk('public')->assertMissing($existingLogoPath);
    Storage::disk('public')->assertMissing($existingMarkPath);
});
