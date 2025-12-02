<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'status')) {
                $table->enum('status', [
                    UserStatus::Pending->value,
                    UserStatus::Active->value,
                    UserStatus::Suspended->value,
                    UserStatus::Deactivated->value,
                ])->default(UserStatus::Active->value);
            }
        });

        DB::table('users')->update(['status' => UserStatus::Active->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
