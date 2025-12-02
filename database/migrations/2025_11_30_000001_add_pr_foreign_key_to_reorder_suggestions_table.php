<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reorder_suggestions') || ! Schema::hasTable('purchase_requisitions')) {
            return;
        }

        Schema::table('reorder_suggestions', function (Blueprint $table): void {
            $table->foreign('pr_id')
                ->references('id')
                ->on('purchase_requisitions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reorder_suggestions')) {
            return;
        }

        Schema::table('reorder_suggestions', function (Blueprint $table): void {
            $table->dropForeign(['pr_id']);
        });
    }
};
