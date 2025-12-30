<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->string('payment_terms', 120)->nullable()->after('moq');
            }

            if (! Schema::hasColumn('suppliers', 'tax_id')) {
                $table->string('tax_id', 120)->nullable()->after('payment_terms');
            }

            if (! Schema::hasColumn('suppliers', 'onboarding_notes')) {
                $table->text('onboarding_notes')->nullable()->after('tax_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (Schema::hasColumn('suppliers', 'onboarding_notes')) {
                $table->dropColumn('onboarding_notes');
            }

            if (Schema::hasColumn('suppliers', 'tax_id')) {
                $table->dropColumn('tax_id');
            }

            if (Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->dropColumn('payment_terms');
            }
        });
    }
};
