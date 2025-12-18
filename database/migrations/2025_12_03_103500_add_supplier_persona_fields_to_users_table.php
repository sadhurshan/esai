<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('supplier_capable')->default(false)->after('status');
            $table->foreignId('default_supplier_id')
                ->nullable()
                ->after('supplier_capable')
                ->constrained('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_supplier_id');
            $table->dropColumn('supplier_capable');
        });
    }
};
