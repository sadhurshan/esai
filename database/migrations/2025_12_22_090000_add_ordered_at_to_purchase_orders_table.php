<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'ordered_at')) {
                $table->timestamp('ordered_at')->nullable()->after('revision_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_orders', 'ordered_at')) {
                $table->dropColumn('ordered_at');
            }
        });
    }
};
