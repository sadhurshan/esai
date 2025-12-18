<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            if (! Schema::hasColumn('parts', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('parts', 'category')) {
                $table->string('category', 120)->nullable()->after('description');
            }

            if (! Schema::hasColumn('parts', 'attributes')) {
                $table->json('attributes')->nullable()->after('meta');
            }

            if (! Schema::hasColumn('parts', 'default_location_code')) {
                $table->string('default_location_code', 64)->nullable()->after('attributes');
            }

            if (! Schema::hasColumn('parts', 'active')) {
                $table->boolean('active')->default(true)->after('default_location_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            if (Schema::hasColumn('parts', 'active')) {
                $table->dropColumn('active');
            }

            if (Schema::hasColumn('parts', 'default_location_code')) {
                $table->dropColumn('default_location_code');
            }

            if (Schema::hasColumn('parts', 'attributes')) {
                $table->dropColumn('attributes');
            }

            if (Schema::hasColumn('parts', 'category')) {
                $table->dropColumn('category');
            }

            if (Schema::hasColumn('parts', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
