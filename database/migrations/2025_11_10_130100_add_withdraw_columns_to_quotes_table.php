<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable()->after('revision_no');
            $table->text('withdraw_reason')->nullable()->after('withdrawn_at');

            $table->index('withdrawn_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex('quotes_withdrawn_at_index');
            $table->dropColumn(['withdrawn_at', 'withdraw_reason']);
        });
    }
};
