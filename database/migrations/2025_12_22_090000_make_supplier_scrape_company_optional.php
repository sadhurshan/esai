<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_scrape_jobs', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('supplier_scrape_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('scraped_suppliers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('scraped_suppliers', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('ai_events', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('ai_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $fallbackCompanyId = DB::table('companies')->orderBy('id')->value('id');

        if ($fallbackCompanyId === null) {
            throw new RuntimeException('Unable to revert supplier scrape company scope without at least one company record.');
        }

        DB::table('supplier_scrape_jobs')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
        DB::table('scraped_suppliers')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
        DB::table('ai_events')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);

        Schema::table('supplier_scrape_jobs', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('supplier_scrape_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::table('scraped_suppliers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('scraped_suppliers', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::table('ai_events', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
        });

        Schema::table('ai_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnUpdate();
        });
    }
};
