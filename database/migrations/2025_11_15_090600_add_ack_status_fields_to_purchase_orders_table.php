<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('purchase_orders', 'ack_status')) {
                $table->enum('ack_status', ['draft', 'sent', 'acknowledged', 'declined'])
                    ->default('draft')
                    ->after('sent_at');
            }

            if (! Schema::hasColumn('purchase_orders', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('ack_status');
            }

            if (! Schema::hasColumn('purchase_orders', 'ack_reason')) {
                $table->text('ack_reason')->nullable()->after('acknowledged_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_orders', 'ack_reason')) {
                $table->dropColumn('ack_reason');
            }

            if (Schema::hasColumn('purchase_orders', 'acknowledged_at')) {
                $table->dropColumn('acknowledged_at');
            }

            if (Schema::hasColumn('purchase_orders', 'ack_status')) {
                $table->dropColumn('ack_status');
            }

            if (Schema::hasColumn('purchase_orders', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
        });
    }
};
