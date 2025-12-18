<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rmas', function (Blueprint $table): void {
            if (! Schema::hasColumn('rmas', 'defect_qty')) {
                $table->unsignedInteger('defect_qty')->nullable()->after('resolution_requested');
            }

            if (! Schema::hasColumn('rmas', 'credit_note_id')) {
                $table->foreignId('credit_note_id')
                    ->nullable()
                    ->after('grn_id')
                    ->constrained('credit_notes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rmas', function (Blueprint $table): void {
            if (Schema::hasColumn('rmas', 'credit_note_id')) {
                $table->dropConstrainedForeignId('credit_note_id');
            }

            if (Schema::hasColumn('rmas', 'defect_qty')) {
                $table->dropColumn('defect_qty');
            }
        });
    }
};
