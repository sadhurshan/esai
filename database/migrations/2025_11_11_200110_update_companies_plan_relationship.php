<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'plan_id')) {
                $table->foreignId('plan_id')->nullable()->after('stripe_id')->constrained('plans')->nullOnDelete();
            }

            if (! Schema::hasColumn('companies', 'notes')) {
                $table->text('notes')->nullable()->after('trial_ends_at');
            }
        });

        if (Schema::hasColumn('companies', 'plan_code') && Schema::hasColumn('companies', 'plan_id')) {
            DB::table('companies')
                ->whereNull('plan_id')
                ->whereNotNull('plan_code')
                ->select('id', 'plan_code')
                ->chunkById(100, function ($companies): void {
                    $planCodes = $companies->pluck('plan_code')->filter()->unique()->all();

                    $plans = DB::table('plans')->whereIn('code', $planCodes)->pluck('id', 'code');

                    foreach ($companies as $company) {
                        $planId = $plans[$company->plan_code] ?? null;

                        if ($planId !== null) {
                            DB::table('companies')->where('id', $company->id)->update([
                                'plan_id' => $planId,
                            ]);
                        }
                    }
                }, 'id');
        }

        if (Schema::hasColumn('companies', 'status')) {
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('pending','pending_verification','active','suspended','trial','closed','rejected') NOT NULL DEFAULT 'active'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'status')) {
            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('pending','pending_verification','active','suspended','rejected') NOT NULL DEFAULT 'pending_verification'");
            }
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'plan_id')) {
                $table->dropConstrainedForeignId('plan_id');
            }

            if (Schema::hasColumn('companies', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};