<?php

use App\Actions\Suppliers\RequireSupplierReverificationAction;
use App\Jobs\AuditSupplierDocumentExpiryJob;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Notifications\SupplierApplicationSubmitted;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

describe('AuditSupplierDocumentExpiryJob', function () {
    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('updates statuses and notifies configured company recipients', function () {
        Carbon::setTestNow(Carbon::parse('2024-01-01'));
        Notification::fake();

        $company = Company::factory()->create([
            'supplier_status' => 'approved',
            'directory_visibility' => 'public',
        ]);
        $owner = User::factory()->owner()->for($company)->create();
        $buyerAdmin = User::factory()->for($company)->create(['role' => 'buyer_admin']);
        $supplierAdmin = User::factory()->for($company)->create(['role' => 'supplier_admin']);

        $company->owner_user_id = $owner->id;
        $company->save();

        SupplierApplication::factory()
            ->approved()
            ->for($company)
            ->create([
                'submitted_by' => $owner->id,
            ]);

        $supplier = Supplier::factory()->for($company)->create();

        $platformAdmin = User::factory()->create([
            'role' => 'platform_super',
            'company_id' => null,
        ]);

        $validDocument = SupplierDocument::factory()
            ->for($supplier, 'supplier')
            ->for($company, 'company')
            ->create([
                'expires_at' => Carbon::now()->addDays(90),
                'status' => 'valid',
            ]);

        $expiringDocument = SupplierDocument::factory()
            ->for($supplier, 'supplier')
            ->for($company, 'company')
            ->create([
                'expires_at' => Carbon::now()->addDays(5),
                'status' => 'valid',
            ]);

        $expiredDocument = SupplierDocument::factory()
            ->for($supplier, 'supplier')
            ->for($company, 'company')
            ->create([
                'expires_at' => Carbon::now()->subDay(),
                'status' => 'valid',
            ]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('send')->twice()->withArgs(function ($recipients, string $eventType, string $title, string $body, string $entityType, ?int $entityId, array $meta) use ($expiringDocument, $expiredDocument) {
            expect($eventType)->toBe('certificate_expiry');
            expect($entityType)->toBe(SupplierDocument::class);
            expect($entityId)->toBeIn([$expiringDocument->id, $expiredDocument->id]);
            expect($meta['status'])->toBeIn(['expiring', 'expired']);
            if ($entityId === $expiredDocument->id) {
                expect($meta['requires_reverification'])->toBeTrue();
            } else {
                expect($meta['requires_reverification'])->toBeFalse();
            }
            expect(count($recipients))->toBe(3);

            return true;
        });

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('updated')->times(3);
        $auditLogger->shouldReceive('created')->once();

        $action = new RequireSupplierReverificationAction(app('db'), $auditLogger);

        (new AuditSupplierDocumentExpiryJob())->handle($notifications, $auditLogger, $action);

        expect($validDocument->fresh()->status)->toBe('valid');
        expect($expiringDocument->fresh()->status)->toBe('expiring');
        expect($expiredDocument->fresh()->status)->toBe('expired');
        expect($company->fresh()->supplier_status->value)->toBe('pending');
        expect($company->fresh()->directory_visibility)->toBe('private');

        $pendingApplications = SupplierApplication::query()
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->count();

        expect($pendingApplications)->toBe(1);

        $pendingApplication = SupplierApplication::query()
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        expect($pendingApplication)->not->toBeNull();
        expect($pendingApplication->documents()->count())->toBe(1);
        expect($pendingApplication->documents()->first()->id)->toBe($expiredDocument->id);

        Notification::assertSentTo(
            $platformAdmin,
            SupplierApplicationSubmitted::class,
            function (SupplierApplicationSubmitted $notification) use ($company): bool {
                return $notification->application->company_id === $company->id;
            }
        );
    });

    it('limits auditing to the provided company id', function () {
        Carbon::setTestNow(Carbon::parse('2024-06-15'));
        Notification::fake();

        $companyA = Company::factory()->create([
            'supplier_status' => 'approved',
            'directory_visibility' => 'public',
        ]);
        $ownerA = User::factory()->owner()->for($companyA)->create();
        $companyA->forceFill(['owner_user_id' => $ownerA->id])->save();
        User::factory()->for($companyA)->create(['role' => 'buyer_admin']);

        $companyB = Company::factory()->create([
            'supplier_status' => 'approved',
            'directory_visibility' => 'public',
        ]);
        $ownerB = User::factory()->owner()->for($companyB)->create();
        $companyB->forceFill(['owner_user_id' => $ownerB->id])->save();
        User::factory()->for($companyB)->create(['role' => 'buyer_admin']);

        SupplierApplication::factory()->approved()->for($companyA)->create([
            'submitted_by' => $ownerA->id,
        ]);
        SupplierApplication::factory()->approved()->for($companyB)->create([
            'submitted_by' => $ownerB->id,
        ]);

        $supplierA = Supplier::factory()->for($companyA)->create();
        $supplierB = Supplier::factory()->for($companyB)->create();

        $targetDocument = SupplierDocument::factory()
            ->for($supplierA, 'supplier')
            ->for($companyA, 'company')
            ->create([
                'expires_at' => Carbon::now()->addDays(3),
                'status' => 'valid',
            ]);

        $unaffectedDocument = SupplierDocument::factory()
            ->for($supplierB, 'supplier')
            ->for($companyB, 'company')
            ->create([
                'expires_at' => Carbon::now()->addDays(3),
                'status' => 'valid',
            ]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('send')->once()->withArgs(function ($recipients, string $eventType, string $title, string $body, string $entityType, ?int $entityId, array $meta) use ($targetDocument) {
            expect($entityId)->toBe($targetDocument->id);
            expect($meta['status'])->toBe('expiring');
            expect($meta['requires_reverification'])->toBeFalse();

            return true;
        });

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('updated')->once();

        $action = new RequireSupplierReverificationAction(app('db'), $auditLogger);

        (new AuditSupplierDocumentExpiryJob($companyA->id))->handle($notifications, $auditLogger, $action);

        expect($targetDocument->fresh()->status)->toBe('expiring');
        expect($unaffectedDocument->fresh()->status)->toBe('valid');
        expect($companyA->fresh()->supplier_status->value)->toBe('approved');
        expect($companyB->fresh()->supplier_status->value)->toBe('approved');

        Notification::assertNothingSent();
    });
});
