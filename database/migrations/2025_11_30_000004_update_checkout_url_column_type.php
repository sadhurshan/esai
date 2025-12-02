<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'checkout_url')) {
            return;
        }

        DB::statement('ALTER TABLE subscriptions MODIFY checkout_url TEXT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('subscriptions', 'checkout_url')) {
            return;
        }

        DB::statement('ALTER TABLE subscriptions MODIFY checkout_url VARCHAR(255) NULL');
    }
};
