<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_locale_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_locale_settings', 'currency_primary')) {
                $table->string('currency_primary', 3)->default('USD')->after('date_format');
            }

            if (! Schema::hasColumn('company_locale_settings', 'currency_display_fx')) {
                $table->boolean('currency_display_fx')->default(false)->after('currency_primary');
            }

            if (! Schema::hasColumn('company_locale_settings', 'uom_base')) {
                $table->string('uom_base', 12)->default('EA')->after('currency_display_fx');
            }

            if (! Schema::hasColumn('company_locale_settings', 'uom_maps')) {
                $table->json('uom_maps')->nullable()->after('uom_base');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_locale_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('company_locale_settings', 'uom_maps')) {
                $table->dropColumn('uom_maps');
            }

            if (Schema::hasColumn('company_locale_settings', 'uom_base')) {
                $table->dropColumn('uom_base');
            }

            if (Schema::hasColumn('company_locale_settings', 'currency_display_fx')) {
                $table->dropColumn('currency_display_fx');
            }

            if (Schema::hasColumn('company_locale_settings', 'currency_primary')) {
                $table->dropColumn('currency_primary');
            }
        });
    }
};
