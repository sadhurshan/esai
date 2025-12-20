<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('plans')
            ->where('code', 'growth')
            ->update([
                'digital_twin_enabled' => true,
                'maintenance_enabled' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('plans')
            ->where('code', 'growth')
            ->update([
                'digital_twin_enabled' => false,
                'maintenance_enabled' => false,
                'updated_at' => now(),
            ]);
    }
};
