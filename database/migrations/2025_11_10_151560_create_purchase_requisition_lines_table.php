<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Duplicate migration retained to avoid renumbering timestamps. See
        // 2025_11_10_151260_create_purchase_requisition_lines_table for the
        // authoritative schema definition.
    }

    public function down(): void
    {
        // No-op: base migration handles teardown.
    }
};
