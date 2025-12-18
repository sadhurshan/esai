<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('supplier_invoicing_enabled')->default(false)->after('credit_notes_enabled');
        });

        $plans = DB::table('plans')->select('id', 'code')->get();

        foreach ($plans as $plan) {
            $code = is_string($plan->code) ? strtolower($plan->code) : null;

            if ($code !== null && in_array($code, ['growth', 'enterprise'], true)) {
                DB::table('plans')->where('id', $plan->id)->update(['supplier_invoicing_enabled' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('supplier_invoicing_enabled');
        });
    }
};
