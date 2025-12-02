<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('max_attempts')->default(5)->after('attempts');
            $table->unsignedInteger('latency_ms')->nullable()->after('max_attempts');
            $table->unsignedSmallInteger('response_code')->nullable()->after('latency_ms');
            $table->mediumText('response_body')->nullable()->after('response_code');
            $table->timestamp('dead_lettered_at')->nullable()->after('delivered_at');
            $table->index(['company_id', 'status']);
            $table->index('dead_lettered_at');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $deliveries = DB::table('webhook_deliveries')
                ->select(['id', 'subscription_id'])
                ->whereNull('company_id')
                ->get();

            foreach ($deliveries as $delivery) {
                $companyId = DB::table('webhook_subscriptions')
                    ->where('id', $delivery->subscription_id)
                    ->value('company_id');

                if ($companyId !== null) {
                    DB::table('webhook_deliveries')
                        ->where('id', $delivery->id)
                        ->update(['company_id' => $companyId]);
                }
            }
        } else {
            DB::statement('UPDATE webhook_deliveries d INNER JOIN webhook_subscriptions s ON s.id = d.subscription_id SET d.company_id = s.company_id WHERE d.company_id IS NULL');
            DB::statement("ALTER TABLE webhook_deliveries MODIFY COLUMN status ENUM('pending','success','failed','dead_letter') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex('webhook_deliveries_company_id_status_index');
            $table->dropIndex('webhook_deliveries_dead_lettered_at_index');
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'company_id',
                'max_attempts',
                'latency_ms',
                'response_code',
                'response_body',
                'dead_lettered_at',
            ]);
        });

        DB::table('webhook_deliveries')
            ->where('status', 'dead_letter')
            ->update(['status' => 'failed']);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE webhook_deliveries MODIFY COLUMN status ENUM('pending','success','failed') DEFAULT 'pending'");
        }
    }
};
