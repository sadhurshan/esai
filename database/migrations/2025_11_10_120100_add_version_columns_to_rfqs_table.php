<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'version_no')) {
                $table->unsignedInteger('version_no')->default(1)->after('version');
            }

            if (! Schema::hasColumn('rfqs', 'current_revision_id')) {
                $table->foreignId('current_revision_id')
                    ->nullable()
                    ->after('version_no')
                    ->constrained('rfq_clarifications')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('rfqs', 'current_revision_id')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropForeign('rfqs_current_revision_id_foreign');
                $table->dropColumn('current_revision_id');
            });
        }

        if (Schema::hasColumn('rfqs', 'version_no')) {
            Schema::table('rfqs', function (Blueprint $table): void {
                $table->dropColumn('version_no');
            });
        }
    }
};
