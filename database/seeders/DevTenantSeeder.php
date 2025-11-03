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
        $companySlug = 'elements-supply-dev';

        $company = DB::table('companies')->where('slug', $companySlug)->first();

        if (! $company) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Elements Supply Demo Co',
                'slug' => $companySlug,
                'status' => 'active',
                'region' => 'NA',
                'rfqs_monthly_used' => 0,
                'storage_used_mb' => 0,
                'plan_code' => 'starter',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $companyId = $company->id;

            DB::table('companies')->where('id', $companyId)->update([
                'status' => 'active',
                'plan_code' => 'starter',
                'updated_at' => $now,
            ]);
        }

        $buyerEmail = 'buyer.admin@example.com';
        $supplierEmail = 'supplier.estimator@example.com';

        $buyer = DB::table('users')->where('email', $buyerEmail)->first();

        if (! $buyer) {
            $buyerId = DB::table('users')->insertGetId([
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
            $buyerId = $buyer->id;

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

        $supplier = DB::table('users')->where('email', $supplierEmail)->first();

        if (! $supplier) {
            $supplierId = DB::table('users')->insertGetId([
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
            $supplierId = $supplier->id;

            DB::table('users')->where('id', $supplierId)->update([
                'role' => 'supplier_estimator',
                'company_id' => $companyId,
                'updated_at' => $now,
            ]);
        }

        $this->syncCompanyUser($companyId, $buyerId, 'buyer_admin');
        $this->syncCompanyUser($companyId, $supplierId, 'supplier_estimator');
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
}
