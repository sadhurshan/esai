<?php

use App\Models\GoodsReceiptLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')
            ->where('documentable_type', GoodsReceiptLine::class)
            ->where('kind', 'po')
            ->update(['kind' => 'grn_attachment']);
    }

    public function down(): void
    {
        DB::table('documents')
            ->where('documentable_type', GoodsReceiptLine::class)
            ->where('kind', 'grn_attachment')
            ->update(['kind' => 'po']);
    }
};
