<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_matches', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_matches', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('invoice_matches', 'deleted_at')) {
                $table->softDeletes();
            }

            $table->index(['company_id', 'result'], 'invoice_matches_company_result_index');
        });

            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                DB::statement(
                    'UPDATE invoice_matches SET company_id = (SELECT company_id FROM invoices WHERE invoices.id = invoice_matches.invoice_id) WHERE company_id IS NULL'
                );
            } else {
                DB::statement('UPDATE invoice_matches im INNER JOIN invoices i ON i.id = im.invoice_id SET im.company_id = i.company_id WHERE im.company_id IS NULL');

                DB::statement('ALTER TABLE invoice_matches MODIFY company_id BIGINT UNSIGNED NOT NULL');
            }
    }

    public function down(): void
    {
        Schema::table('invoice_matches', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_matches', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('invoice_matches', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            $table->dropIndex('invoice_matches_company_result_index');
        });
    }
};
