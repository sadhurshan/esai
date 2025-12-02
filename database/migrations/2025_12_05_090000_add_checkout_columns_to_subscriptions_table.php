<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('checkout_session_id')->nullable()->after('stripe_plan');
            $table->string('checkout_status')->nullable()->after('checkout_session_id');
            $table->text('checkout_url')->nullable()->after('checkout_status');
            $table->timestamp('checkout_started_at')->nullable()->after('checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'checkout_session_id',
                'checkout_status',
                'checkout_url',
                'checkout_started_at',
            ]);
        });
    }
};
