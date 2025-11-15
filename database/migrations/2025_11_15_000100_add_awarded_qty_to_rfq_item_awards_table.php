<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_item_awards', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfq_item_awards', 'awarded_qty')) {
                $table->unsignedInteger('awarded_qty')->nullable()->after('quote_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rfq_item_awards', function (Blueprint $table): void {
            if (Schema::hasColumn('rfq_item_awards', 'awarded_qty')) {
                $table->dropColumn('awarded_qty');
            }
        });
    }
};
