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
        $attributes = $this->sanitizeAttributes($payload, $owner);

        return DB::transaction(function () use ($attributes, $owner): Company {
            $owner->loadMissing('company');
            $existingCompany = $owner->company()->lockForUpdate()->first();

            if ($existingCompany instanceof Company) {
                $companyBefore = $existingCompany->getOriginal();

                $existingCompany->fill(array_merge($attributes, [
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

                return $existingCompany->fresh();
            }

            $slug = $this->uniqueSlug($attributes['name']);

            $company = new Company();
            $company->fill(array_merge($attributes, [
                'slug' => $slug,
                'status' => CompanyStatus::PendingVerification,
                'supplier_status' => CompanySupplierStatus::None->value,
                'directory_visibility' => 'private',
                'supplier_profile_completed_at' => null,
                'owner_user_id' => $owner->id,
            ]));

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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeAttributes(array $payload, User $owner): array
    {
        $country = $payload['country'] ?? null;
        $country = is_string($country) && $country !== '' ? strtoupper($country) : null;

        $emailDomain = $payload['email_domain'] ?? null;
        $emailDomain = is_string($emailDomain) && $emailDomain !== '' ? strtolower($emailDomain) : null;

        return [
            'name' => $payload['name'],
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
}
