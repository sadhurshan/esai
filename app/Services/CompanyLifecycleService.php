<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Enums\SupplierApplicationStatus;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\User;
use App\Notifications\SupplierApplicationApproved;
use App\Notifications\SupplierApplicationRejected;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CompanyLifecycleService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db,
    ) {}

    public function createBuyerCompanyForUser(User $user): Company
    {
        if ($user->company_id !== null && $user->company !== null) {
            return $user->company;
        }

        return $this->db->transaction(function () use ($user): Company {
            $name = $this->defaultCompanyName($user);
            $slug = $this->uniqueSlug($name);

            $company = new Company([
                'name' => $name,
                'slug' => $slug,
                'status' => CompanyStatus::PendingVerification,
                'supplier_status' => CompanySupplierStatus::None,
                'is_verified' => false,
                'owner_user_id' => $user->id,
            ]);

            $company->save();

            $user->forceFill([
                'role' => 'owner',
                'company_id' => $company->id,
            ])->save();

            DB::table('company_user')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $user->role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->auditLogger->created($company);

            return $company->fresh();
        });
    }

    public function approveSupplier(SupplierApplication $application, User $reviewer, ?string $notes = null): SupplierApplication
    {
        if ($application->status !== SupplierApplicationStatus::Pending) {
            throw new InvalidArgumentException('Only pending applications can be approved.');
        }

        return $this->db->transaction(function () use ($application, $reviewer): SupplierApplication {
            $company = $application->company()->lockForUpdate()->firstOrFail();
            $companyBefore = $company->getOriginal();

            $company->fill([
                'supplier_status' => CompanySupplierStatus::Approved,
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => $reviewer->id,
            ]);

            $companyChanges = $company->getDirty();
            $company->save();

            if ($companyChanges !== []) {
                $this->auditLogger->updated($company, $companyBefore, $companyChanges);
            }

            $form = Arr::wrap($application->form_json);
            $supplier = Supplier::firstOrNew(['company_id' => $company->id]);

            $supplier->fill(array_filter([
                'name' => $form['company_name'] ?? $company->name,
                'capabilities' => $form['capabilities'] ?? null,
                'materials' => $form['materials'] ?? null,
                'website' => $form['website'] ?? $company->website,
                'email' => $form['contact_email'] ?? $company->primary_contact_email,
                'phone' => $form['contact_phone'] ?? $company->phone,
                'city' => $form['city'] ?? null,
                'country' => isset($form['country']) ? Str::upper((string) $form['country']) : ($company->country ?? null),
            ], static fn ($value) => $value !== null));

            if (! $supplier->exists) {
                // TODO: clarify default supplier metrics with spec owners.
                $supplier->fill([
                    'rating' => 0,
                    'rating_avg' => 0,
                    'location_region' => $form['region'] ?? ($company->region ?? 'unspecified'),
                    'min_order_qty' => 0,
                    'avg_response_hours' => 0,
                ]);
            }

            $supplier->status = 'approved';
            $supplier->save();

            $applicationBefore = $application->getOriginal();
            $application->fill([
                'status' => SupplierApplicationStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'notes' => $notes ?? $application->notes,
            ]);

            $applicationChanges = $application->getDirty();
            $application->save();

            if ($applicationChanges !== []) {
                $this->auditLogger->updated($application, $applicationBefore, $applicationChanges);
            }

            if ($company->owner instanceof User) {
                Notification::send($company->owner, new SupplierApplicationApproved($application));
            }

            return $application->fresh(['company', 'reviewedBy']);
        });
    }

    public function rejectSupplier(SupplierApplication $application, User $reviewer, string $notes): SupplierApplication
    {
        if ($application->status !== SupplierApplicationStatus::Pending) {
            throw new InvalidArgumentException('Only pending applications can be rejected.');
        }

        return $this->db->transaction(function () use ($application, $reviewer, $notes): SupplierApplication {
            $company = $application->company()->lockForUpdate()->firstOrFail();
            $companyBefore = $company->getOriginal();

            $company->fill([
                'supplier_status' => CompanySupplierStatus::Rejected,
                'is_verified' => false,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            $companyChanges = $company->getDirty();
            $company->save();

            if ($companyChanges !== []) {
                $this->auditLogger->updated($company, $companyBefore, $companyChanges);
            }

            $applicationBefore = $application->getOriginal();
            $application->fill([
                'status' => SupplierApplicationStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'notes' => $notes,
            ]);

            $applicationChanges = $application->getDirty();
            $application->save();

            if ($applicationChanges !== []) {
                $this->auditLogger->updated($application, $applicationBefore, $applicationChanges);
            }

            if ($company->owner instanceof User) {
                Notification::send($company->owner, new SupplierApplicationRejected($application));
            }

            return $application->fresh(['company', 'reviewedBy']);
        });
    }

    private function defaultCompanyName(User $user): string
    {
        $domain = Str::after($user->email, '@');

        if ($domain !== $user->email) {
            return Str::headline(Str::before($domain, '.')).' Holdings';
        }

        return $user->name.' Company';
    }

    private function uniqueSlug(string $baseName): string
    {
        $base = Str::slug($baseName);

        if ($base === '') {
            $base = Str::slug(Str::random(12));
        }

        $slug = $base;
        $suffix = 1;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = substr($base, 0, 150).'-'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $slug;
    }
}
