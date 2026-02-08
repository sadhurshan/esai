<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'start_mode')) {
                $table->enum('start_mode', ['buyer', 'supplier'])->default('buyer')->after('status');
            }
        });

    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        if (Schema::hasColumn('companies', 'start_mode')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropColumn('start_mode');
            });
        }
    }
};
