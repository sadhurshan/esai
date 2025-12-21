<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scraped_suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('scraped_suppliers', 'status')) {
                $table->enum('status', ['pending', 'approved', 'discarded'])->default('pending')->after('metadata_json');
            }

            if (! Schema::hasColumn('scraped_suppliers', 'approved_supplier_id')) {
                $table->foreignId('approved_supplier_id')->nullable()->after('status')->constrained('suppliers')->nullOnDelete();
            }

            if (! Schema::hasColumn('scraped_suppliers', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('approved_supplier_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('scraped_suppliers', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (! Schema::hasColumn('scraped_suppliers', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scraped_suppliers', function (Blueprint $table): void {
            if (Schema::hasColumn('scraped_suppliers', 'review_notes')) {
                $table->dropColumn('review_notes');
            }

            if (Schema::hasColumn('scraped_suppliers', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('scraped_suppliers', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn('reviewed_by');
            }

            if (Schema::hasColumn('scraped_suppliers', 'approved_supplier_id')) {
                $table->dropForeign(['approved_supplier_id']);
                $table->dropColumn('approved_supplier_id');
            }

            if (Schema::hasColumn('scraped_suppliers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
