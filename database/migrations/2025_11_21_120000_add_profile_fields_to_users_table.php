<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 120)->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('job_title');
            }

            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 10)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('locale');
            }

            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = ['job_title', 'phone', 'locale', 'timezone', 'avatar_path'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
