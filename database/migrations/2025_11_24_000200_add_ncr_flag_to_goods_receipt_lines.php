<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->boolean('ncr_flag')->default(false)->after('attachment_ids');
            $table->index(['goods_receipt_note_id', 'ncr_flag'], 'goods_receipt_lines_note_ncr_index');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->dropIndex('goods_receipt_lines_note_ncr_index');
            $table->dropColumn('ncr_flag');
        });
    }
};
