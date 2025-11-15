<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('goods_receipt_notes', 'reference')) {
                $table->string('reference', 120)->nullable()->after('status');
            }

            if (! Schema::hasColumn('goods_receipt_notes', 'notes')) {
                $table->text('notes')->nullable()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('goods_receipt_notes', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('goods_receipt_notes', 'reference')) {
                $table->dropColumn('reference');
            }
        });
    }
};
