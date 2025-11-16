<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Events\CompanyPendingVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('self registers a company owner and issues a session token', function (): void {
    Event::fake([CompanyPendingVerification::class]);

    Storage::fake();

    $payload = [
        'name' => 'Casey Owner',
        'email' => 'casey@example.com',
        'password' => 'Passw0rd!',
        'password_confirmation' => 'Passw0rd!',
        'company_name' => 'Axiom Manufacturing',
        'company_domain' => 'axiom.example',
        'address' => '100 Industrial Way',
        'phone' => '+1-555-1000',
        'country' => 'US',
        'registration_no' => 'REG-AXIOM-001',
        'tax_id' => 'TAX-AXIOM-999',
        'website' => 'https://axiom.example',
        'company_documents' => [
            [
                'type' => 'registration',
                'file' => UploadedFile::fake()->create('registration.pdf', 300, 'application/pdf'),
            ],
            [
                'type' => 'tax',
                'file' => UploadedFile::fake()->create('tax.png', 250, 'image/png'),
            ],
        ],
    ];

    $response = $this->post('/api/auth/register', $payload, ['Accept' => 'application/json']);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.user.email', 'casey@example.com')
        ->assertJsonPath('data.user.role', 'owner')
        ->assertJsonPath('data.company.status', CompanyStatus::PendingVerification->value)
        ->assertJsonPath('data.company.name', 'Axiom Manufacturing')
        ->assertJsonStructure(['data' => ['token']]);

    $user = User::whereEmail('casey@example.com')->firstOrFail();
    $company = $user->company()->firstOrFail();

    expect($company->supplier_status)->toBe(CompanySupplierStatus::None)
        ->and($company->directory_visibility)->toBe('private')
        ->and($company->owner_user_id)->toBe($user->id)
        ->and($company->registration_no)->toBe('REG-AXIOM-001')
        ->and($company->tax_id)->toBe('TAX-AXIOM-999')
        ->and($company->website)->toBe('https://axiom.example');

    $documents = $company->documents()->get();

    expect($documents)->toHaveCount(2);

    $disk = config('filesystems.default');

    foreach ($documents as $document) {
        Storage::disk($disk)->assertExists($document->path);
    }

    Event::assertDispatched(CompanyPendingVerification::class, function (CompanyPendingVerification $event) use ($company): bool {
        return $event->company->is($company);
    });
});
