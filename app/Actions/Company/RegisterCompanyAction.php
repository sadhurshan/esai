<?php

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierPersonaService;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterCompanyAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SupplierPersonaService $supplierPersonaService,
    ) {}

    public function execute(array $payload, User $owner): Company
    {
        $startMode = $this->normalizeStartMode($payload['start_mode'] ?? null);
        $attributes = $this->sanitizeAttributes($payload, $owner, $startMode);

        return DB::transaction(function () use ($attributes, $owner, $startMode): Company {
            $owner->loadMissing('company');
            $existingCompany = $owner->company()->lockForUpdate()->first();

            if ($existingCompany instanceof Company) {
                $companyBefore = $existingCompany->getOriginal();

                $supplierUpdates = [];

                if ($startMode === 'supplier' && $existingCompany->supplier_status === CompanySupplierStatus::None) {
                    $supplierUpdates = [
                        'supplier_status' => CompanySupplierStatus::Pending,
                        'supplier_profile_completed_at' => null,
                    ];
                }

                $existingCompany->fill(array_merge($attributes, $supplierUpdates, [
                    'status' => CompanyStatus::PendingVerification,
                    'owner_user_id' => $owner->id,
                    'rejection_reason' => null,
                ]));

                if ($existingCompany->isDirty('name')) {
                    $existingCompany->slug = $this->uniqueSlug($existingCompany->name, $existingCompany->id);
                }

                $companyChanges = $existingCompany->getDirty();

                if ($companyChanges !== []) {
                    $existingCompany->save();
                    $this->auditLogger->updated($existingCompany, $companyBefore, $companyChanges);
                }

                DB::table('company_user')->updateOrInsert(
                    [
                        'company_id' => $existingCompany->id,
                        'user_id' => $owner->id,
                    ],
                    [
                        'role' => $owner->role,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if ($startMode === 'supplier') {
                    $this->ensureSupplierPersona($existingCompany);
                }

                return $existingCompany->fresh();
            }

            $slug = $this->uniqueSlug($attributes['name']);

            $company = new Company();
            $company->fill(array_merge($attributes, [
                'slug' => $slug,
                'status' => CompanyStatus::PendingVerification,
                'supplier_status' => $startMode === 'supplier'
                    ? CompanySupplierStatus::Pending->value
                    : CompanySupplierStatus::None->value,
                'directory_visibility' => 'private',
                'supplier_profile_completed_at' => null,
                'owner_user_id' => $owner->id,
            ]));

            $company->save();

            if ($startMode === 'supplier') {
                $this->ensureSupplierPersona($company);
            }

            $owner->forceFill(['company_id' => $company->id])->save();

            DB::table('company_user')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'user_id' => $owner->id,
                ],
                [
                    'role' => $owner->role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->auditLogger->created($company);

            return $company;
        });
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = Str::random(12);
        }

        $slug = $base;
        $suffix = 1;

        while (
            Company::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, static fn ($query) => $query->where('id', '<>', $ignoreId))
                ->exists()
        ) {
            $slug = substr($base, 0, 150).'-'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeAttributes(array $payload, User $owner, string $startMode): array
    {
        $country = $payload['country'] ?? null;
        $country = is_string($country) && $country !== '' ? strtoupper($country) : null;

        $emailDomain = $payload['email_domain'] ?? null;
        $emailDomain = is_string($emailDomain) && $emailDomain !== '' ? strtolower($emailDomain) : null;

        return [
            'name' => $payload['name'],
            'start_mode' => $startMode,
            'registration_no' => $payload['registration_no'] ?? null,
            'tax_id' => $payload['tax_id'] ?? null,
            'country' => $country,
            'email_domain' => $emailDomain,
            'primary_contact_name' => $payload['primary_contact_name'] ?? $owner->name,
            'primary_contact_email' => $payload['primary_contact_email'] ?? $owner->email,
            'primary_contact_phone' => $payload['primary_contact_phone'] ?? ($payload['phone'] ?? null),
            'address' => $payload['address'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'website' => $payload['website'] ?? null,
            'region' => $payload['region'] ?? null,
        ];
    }

    private function normalizeStartMode(mixed $value): string
    {
        if (! is_string($value)) {
            return 'buyer';
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['buyer', 'supplier'], true) ? $normalized : 'buyer';
    }

    private function ensureSupplierPersona(Company $company): void
    {
        $supplier = Supplier::query()->where('company_id', $company->id)->first();

        if (! $supplier instanceof Supplier) {
            $supplier = new Supplier([
                'company_id' => $company->id,
                'name' => $company->name,
                'status' => 'pending',
                'email' => $company->primary_contact_email,
                'phone' => $company->primary_contact_phone,
                'address' => $company->address,
                'country' => $company->country,
                'website' => $company->website,
                'capabilities' => [],
            ]);

            $supplier->save();
        }

        $this->supplierPersonaService->ensureBuyerContact($supplier, $company->id, $company, false);
    }
}
