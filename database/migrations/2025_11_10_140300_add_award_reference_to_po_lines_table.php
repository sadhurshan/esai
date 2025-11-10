<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('po_lines', 'rfq_item_award_id')) {
                $table->foreignId('rfq_item_award_id')
                    ->nullable()
                    ->after('rfq_item_id')
                    ->constrained('rfq_item_awards')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('po_lines', 'rfq_item_award_id')) {
            Schema::table('po_lines', function (Blueprint $table): void {
                $table->dropForeign(['rfq_item_award_id']);
                $table->dropColumn('rfq_item_award_id');
            });
        }
    }
};
