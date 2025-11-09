<?php

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterCompanyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(array $payload, User $owner): Company
    {
        return DB::transaction(function () use ($payload, $owner): Company {
            $owner->loadMissing('company');
            $existingCompany = $owner->company()->lockForUpdate()->first();

            if ($existingCompany instanceof Company) {
                $companyBefore = $existingCompany->getOriginal();

                $existingCompany->fill([
                    'name' => $payload['name'],
                    'registration_no' => $payload['registration_no'],
                    'tax_id' => $payload['tax_id'],
                    'country' => strtoupper($payload['country']),
                    'email_domain' => strtolower($payload['email_domain']),
                    'primary_contact_name' => $payload['primary_contact_name'],
                    'primary_contact_email' => $payload['primary_contact_email'],
                    'primary_contact_phone' => $payload['primary_contact_phone'],
                    'address' => $payload['address'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                    'website' => $payload['website'] ?? null,
                    'region' => $payload['region'] ?? null,
                    'status' => CompanyStatus::PendingVerification,
                    'owner_user_id' => $owner->id,
                    'rejection_reason' => null,
                ]);

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

                return $existingCompany->fresh();
            }

            $slug = $this->uniqueSlug($payload['name']);

            $company = new Company();
            $company->fill([
                'name' => $payload['name'],
                'slug' => $slug,
                'status' => CompanyStatus::PendingVerification,
                'supplier_status' => CompanySupplierStatus::None->value,
                'directory_visibility' => 'private',
                'supplier_profile_completed_at' => null,
                'registration_no' => $payload['registration_no'],
                'tax_id' => $payload['tax_id'],
                'country' => strtoupper($payload['country']),
                'email_domain' => strtolower($payload['email_domain']),
                'primary_contact_name' => $payload['primary_contact_name'],
                'primary_contact_email' => $payload['primary_contact_email'],
                'primary_contact_phone' => $payload['primary_contact_phone'],
                'address' => $payload['address'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'website' => $payload['website'] ?? null,
                'region' => $payload['region'] ?? null,
                'owner_user_id' => $owner->id,
            ]);

            $company->save();

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
}
