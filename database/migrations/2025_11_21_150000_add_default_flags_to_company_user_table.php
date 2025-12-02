<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('role');
            $table->timestamp('last_used_at')->nullable()->after('is_default');
            $table->index(['user_id', 'is_default'], 'company_user_user_default_index');
        });

        DB::table('company_user as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->whereColumn('u.company_id', 'cu.company_id')
            ->update(['cu.is_default' => true]);

        $usersWithoutDefault = DB::table('company_user')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('SUM(is_default) = 0')
            ->pluck('user_id');

        foreach ($usersWithoutDefault as $userId) {
            $membership = DB::table('company_user')
                ->where('user_id', $userId)
                ->orderBy('created_at')
                ->first();

            if ($membership === null) {
                continue;
            }

            DB::table('company_user')
                ->where('id', $membership->id)
                ->update(['is_default' => true]);

            DB::table('users')
                ->where('id', $userId)
                ->whereNull('company_id')
                ->update(['company_id' => $membership->company_id]);
        }
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table): void {
            $table->dropIndex('company_user_user_default_index');
            $table->dropColumn(['is_default', 'last_used_at']);
        });
    }
};
