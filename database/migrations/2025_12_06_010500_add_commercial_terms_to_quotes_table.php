<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotes', 'incoterm')) {
                $table->string('incoterm', 8)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('quotes', 'payment_terms')) {
                $table->string('payment_terms', 120)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('quotes', 'incoterm')) {
                $table->dropColumn('incoterm');
            }

            if (Schema::hasColumn('quotes', 'payment_terms')) {
                $table->dropColumn('payment_terms');
            }
        });
    }
};
