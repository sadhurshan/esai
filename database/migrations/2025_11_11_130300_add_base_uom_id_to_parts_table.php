<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->foreignId('base_uom_id')->nullable()->after('uom')->constrained('uoms')->nullOnDelete();
            $table->index('base_uom_id');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            if (Schema::hasColumn('parts', 'base_uom_id')) {
                $table->dropForeign(['base_uom_id']);
                $table->dropIndex('parts_base_uom_id_index');
                $table->dropColumn('base_uom_id');
            }
        });
    }
};
