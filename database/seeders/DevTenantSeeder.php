<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevTenantSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $buyerEmail = 'buyer.admin@example.com';
        $supplierEmail = 'supplier.estimator@example.com';
        $planIds = DB::table('plans')
            ->whereIn('code', ['growth', 'starter'])
            ->pluck('id', 'code');

        if (! isset($planIds['growth'], $planIds['starter'])) {
            throw new \RuntimeException('Growth and Starter plans must exist before running DevTenantSeeder.');
        }

        $buyerCompanyId = $this->seedBuyerCompany($now, (int) $planIds['growth'], $buyerEmail);
        $buyerId = $this->seedBuyerUser($buyerCompanyId, $buyerEmail, $now);
        $this->ensureBuyerSubscription($buyerCompanyId, $buyerId, $buyerEmail, $now);

        [$supplierCompanyId, $supplierId] = $this->seedSupplierTenant(
            $now,
            (int) $planIds['starter'],
            $supplierEmail
        );

        $this->seedSupplierProfile($supplierCompanyId, $supplierEmail, $now);

        $this->syncCompanyUser($buyerCompanyId, $buyerId, 'buyer_admin');
        $this->syncCompanyUser($supplierCompanyId, $supplierId, 'supplier_estimator');
    }

    private function syncCompanyUser(int $companyId, int $userId, string $role): void
    {
        $existing = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('company_user')
                ->where('id', $existing->id)
                ->update([
                    'role' => $role,
                    'updated_at' => Carbon::now(),
                ]);

            return;
        }

        DB::table('company_user')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function seedBuyerCompany(Carbon $now, int $planId, string $buyerEmail): int
    {
        $slug = 'elements-supply-dev';
        $company = DB::table('companies')->where('slug', $slug)->first();

        $data = [
            'name' => 'Elements Supply Demo Co',
            'status' => 'active',
            'region' => 'NA',
            'rfqs_monthly_used' => optional($company)->rfqs_monthly_used ?? 0,
            'storage_used_mb' => optional($company)->storage_used_mb ?? 0,
            'plan_code' => 'growth',
            'plan_id' => $planId,
            'registration_no' => optional($company)->registration_no ?: 'ES-DEV-001',
            'tax_id' => optional($company)->tax_id ?: 'TAX-123456',
            'country' => optional($company)->country ?: 'US',
            'email_domain' => optional($company)->email_domain ?: 'example.com',
            'primary_contact_name' => optional($company)->primary_contact_name ?: 'Buyer Admin',
            'primary_contact_email' => optional($company)->primary_contact_email ?: $buyerEmail,
            'primary_contact_phone' => optional($company)->primary_contact_phone ?: '+1-555-123-4567',
            'updated_at' => $now,
        ];

        if (! $company) {
            return (int) DB::table('companies')->insertGetId(array_merge($data, [
                'slug' => $slug,
                'created_at' => $now,
            ]));
        }

        DB::table('companies')->where('id', $company->id)->update($data);

        return (int) $company->id;
    }

    private function seedBuyerUser(int $companyId, string $buyerEmail, Carbon $now): int
    {
        $buyer = DB::table('users')->where('email', $buyerEmail)->first();

        if (! $buyer) {
            $buyerId = (int) DB::table('users')->insertGetId([
                'name' => 'Buyer Admin',
                'email' => $buyerEmail,
                'password' => Hash::make('password'),
                'role' => 'buyer_admin',
                'company_id' => $companyId,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $buyerId = (int) $buyer->id;

            DB::table('users')->where('id', $buyerId)->update([
                'role' => 'buyer_admin',
                'company_id' => $companyId,
                'updated_at' => $now,
            ]);
        }

        DB::table('companies')->where('id', $companyId)->update([
            'owner_user_id' => $buyerId,
            'updated_at' => $now,
        ]);

        return $buyerId;
    }

    private function ensureBuyerSubscription(int $companyId, int $buyerId, string $buyerEmail, Carbon $now): void
    {
        $customer = DB::table('customers')->where('company_id', $companyId)->first();
        $customerStripeId = 'cus_dev_'.$companyId;

        if (! $customer) {
            $customerId = (int) DB::table('customers')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Elements Supply Demo Co',
                'email' => $buyerEmail,
                'stripe_id' => $customerStripeId,
                'pm_type' => null,
                'pm_last_four' => null,
                'default_payment_method' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $customerId = (int) $customer->id;

            DB::table('customers')->where('id', $customerId)->update([
                'name' => $customer->name ?: 'Elements Supply Demo Co',
                'email' => $customer->email ?: $buyerEmail,
                'stripe_id' => $customerStripeId,
                'updated_at' => $now,
            ]);
        }

        $subscriptionStripeId = 'sub_dev_'.$companyId;
        $subscription = DB::table('subscriptions')->where('stripe_id', $subscriptionStripeId)->first();

        if (! $subscription) {
            DB::table('subscriptions')->insert([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'name' => 'default',
                'stripe_id' => $subscriptionStripeId,
                'stripe_status' => 'active',
                'stripe_plan' => 'growth',
                'quantity' => 1,
                'trial_ends_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('subscriptions')->where('id', $subscription->id)->update([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'name' => 'default',
                'stripe_id' => $subscriptionStripeId,
                'stripe_status' => 'active',
                'stripe_plan' => 'growth',
                'quantity' => 1,
                'trial_ends_at' => null,
                'ends_at' => null,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedSupplierTenant(Carbon $now, int $planId, string $supplierEmail): array
    {
        $slug = 'precision-fabrication-partners';
        $company = DB::table('companies')->where('slug', $slug)->first();

        $data = [
            'name' => 'Precision Fabrication Partners',
            'status' => 'active',
            'supplier_status' => 'approved',
            'directory_visibility' => 'public',
            'supplier_profile_completed_at' => optional($company)->supplier_profile_completed_at ?? $now,
            'region' => 'NA',
            'rfqs_monthly_used' => optional($company)->rfqs_monthly_used ?? 0,
            'storage_used_mb' => optional($company)->storage_used_mb ?? 0,
            'plan_code' => 'starter',
            'plan_id' => $planId,
            'registration_no' => optional($company)->registration_no ?: 'PF-DEV-001',
            'tax_id' => optional($company)->tax_id ?: 'SUP-789456',
            'country' => optional($company)->country ?: 'US',
            'email_domain' => optional($company)->email_domain ?: 'precisionfab.example',
            'primary_contact_name' => optional($company)->primary_contact_name ?: 'Supplier Estimator',
            'primary_contact_email' => optional($company)->primary_contact_email ?: $supplierEmail,
            'primary_contact_phone' => optional($company)->primary_contact_phone ?: '+1-555-987-6543',
            'address' => optional($company)->address ?: '200 Supplier Ave, Austin, TX 73301',
            'phone' => optional($company)->phone ?: '+1-555-987-6543',
            'website' => optional($company)->website ?: 'https://precisionfab.example',
            'is_verified' => true,
            'verified_at' => optional($company)->verified_at ?? $now,
            'updated_at' => $now,
        ];

        if (! $company) {
            $companyId = (int) DB::table('companies')->insertGetId(array_merge($data, [
                'slug' => $slug,
                'created_at' => $now,
            ]));
        } else {
            DB::table('companies')->where('id', $company->id)->update($data);
            $companyId = (int) $company->id;
        }

        $supplier = DB::table('users')->where('email', $supplierEmail)->first();

        if (! $supplier) {
            $supplierId = (int) DB::table('users')->insertGetId([
                'name' => 'Supplier Estimator',
                'email' => $supplierEmail,
                'password' => Hash::make('password'),
                'role' => 'supplier_estimator',
                'company_id' => $companyId,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $supplierId = (int) $supplier->id;

            DB::table('users')->where('id', $supplierId)->update([
                'role' => 'supplier_estimator',
                'company_id' => $companyId,
                'updated_at' => $now,
            ]);
        }

        DB::table('companies')->where('id', $companyId)->update([
            'owner_user_id' => $supplierId,
            'updated_at' => $now,
        ]);

        DB::table('company_user')
            ->where('user_id', $supplierId)
            ->where('company_id', '!=', $companyId)
            ->delete();

        return [$companyId, $supplierId];
    }

    private function seedSupplierProfile(int $companyId, string $supplierEmail, Carbon $now): void
    {
        $capabilities = [
            'methods' => ['CNC Milling', 'CNC Turning', 'Sheet Metal Fabrication'],
            'materials' => ['Aluminum 6061', 'Stainless Steel 304', 'Titanium Grade 5'],
            'certifications' => ['ISO 9001', 'AS9100'],
            'finishes' => ['Anodizing', 'Powder Coat', 'Passivation'],
            'industries' => ['Aerospace', 'Robotics', 'Medical Devices'],
        ];

        $profile = DB::table('suppliers')->where('company_id', $companyId)->first();

        if (! $profile) {
            DB::table('suppliers')->insert([
                'company_id' => $companyId,
                'name' => 'Precision Fabrication Partners',
                'capabilities' => json_encode($capabilities),
                'email' => $supplierEmail,
                'phone' => '+1-555-987-6543',
                'website' => 'https://precisionfab.example',
                'address' => '200 Supplier Ave, Austin, TX 73301',
                'country' => 'US',
                'city' => 'Austin',
                'status' => 'approved',
                'geo_lat' => 30.2672,
                'geo_lng' => -97.7431,
                'lead_time_days' => 7,
                'moq' => 5,
                'rating_avg' => 4.80,
                'risk_grade' => 'low',
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('suppliers')->where('id', $profile->id)->update([
            'name' => $profile->name ?: 'Precision Fabrication Partners',
            'capabilities' => json_encode($capabilities),
            'email' => $supplierEmail,
            'phone' => '+1-555-987-6543',
            'website' => $profile->website ?: 'https://precisionfab.example',
            'address' => $profile->address ?: '200 Supplier Ave, Austin, TX 73301',
            'country' => $profile->country ?: 'US',
            'city' => $profile->city ?: 'Austin',
            'status' => 'approved',
            'geo_lat' => $profile->geo_lat ?? 30.2672,
            'geo_lng' => $profile->geo_lng ?? -97.7431,
            'lead_time_days' => $profile->lead_time_days ?? 7,
            'moq' => $profile->moq ?? 5,
            'rating_avg' => $profile->rating_avg ?? 4.80,
            'risk_grade' => $profile->risk_grade ?? 'low',
            'verified_at' => $profile->verified_at ?: $now,
            'updated_at' => $now,
        ]);
    }
}
