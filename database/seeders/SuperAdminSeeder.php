<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $email = config('app.super_admin_email') ?? env('SUPER_ADMIN_EMAIL', 'super.admin@elements-supply.ai');
        $password = config('app.super_admin_password') ?? env('SUPER_ADMIN_PASSWORD', 'super-admin-password');
        $planCode = env('SUPER_ADMIN_PLAN_CODE', 'enterprise');

        $planId = DB::table('plans')->where('code', $planCode)->value('id');

        if (! $planId) {
            throw new RuntimeException(sprintf('Plan with code "%s" must exist before running SuperAdminSeeder.', $planCode));
        }

        $companyId = $this->upsertCompany($planId, $planCode, $email, $now);
        $this->ensureFeatureFlags($companyId, $now);

        $userId = $this->upsertUser($companyId, $email, $password, $now);
        $this->syncCompanyUser($companyId, $userId, $now);
        $this->ensurePlatformAdmin($userId, $now);

        DB::table('companies')
            ->where('id', $companyId)
            ->update([
                'owner_user_id' => $userId,
                'updated_at' => $now,
            ]);

        $this->command?->info(sprintf('Super admin seeded for %s (user #%d, company #%d).', $email, $userId, $companyId));
    }

    private function upsertCompany(int $planId, string $planCode, string $contactEmail, Carbon $now): int
    {
        $slug = 'elements-platform-hq';
        $company = DB::table('companies')->where('slug', $slug)->first();

        $payload = [
            'name' => 'Elements Supply HQ',
            'slug' => $slug,
            'status' => 'active',
            'region' => 'NA',
            'plan_id' => $planId,
            'plan_code' => $planCode,
            'email_domain' => 'elements-supply.ai',
            'primary_contact_name' => 'Platform Operations',
            'primary_contact_email' => $contactEmail,
            'primary_contact_phone' => '+1-555-000-0000',
            'registration_no' => 'ES-HQ-001',
            'tax_id' => 'HQ-99-0000',
            'country' => 'US',
            'updated_at' => $now,
        ];

        if ($company) {
            DB::table('companies')->where('id', $company->id)->update($payload);

            return (int) $company->id;
        }

        return (int) DB::table('companies')->insertGetId(array_merge($payload, [
            'created_at' => $now,
        ]));
    }

    private function ensureFeatureFlags(int $companyId, Carbon $now): void
    {
        DB::table('company_feature_flags')->upsert([
            [
                'company_id' => $companyId,
                'key' => 'admin_console_enabled',
                'value' => json_encode(['enabled' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['company_id', 'key'], ['value', 'updated_at']);
    }

    private function upsertUser(int $companyId, string $email, string $password, Carbon $now): int
    {
        $user = DB::table('users')->where('email', $email)->first();

        $payload = [
            'name' => 'Platform Super Admin',
            'company_id' => $companyId,
            'role' => 'platform_super',
            'password' => Hash::make($password),
            'remember_token' => Str::random(40),
            'updated_at' => $now,
        ];

        if ($user) {
            DB::table('users')->where('id', $user->id)->update($payload);

            return (int) $user->id;
        }

        return (int) DB::table('users')->insertGetId(array_merge($payload, [
            'email' => $email,
            'created_at' => $now,
        ]));
    }

    private function syncCompanyUser(int $companyId, int $userId, Carbon $now): void
    {
        $record = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($record) {
            DB::table('company_user')
                ->where('id', $record->id)
                ->update([
                    'role' => 'platform_super',
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('company_user')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => 'platform_super',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensurePlatformAdmin(int $userId, Carbon $now): void
    {
        if (! DB::getSchemaBuilder()->hasTable('platform_admins')) {
            return;
        }

        DB::table('platform_admins')->updateOrInsert(
            ['user_id' => $userId],
            [
                'role' => 'super',
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
}
