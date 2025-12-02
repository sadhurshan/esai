<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfq_items', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('rfq_id')->constrained('companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('rfq_items', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('rfq_items', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('rfq_items', 'part_number')) {
                $table->string('part_number', 160)->nullable()->after('line_no');
            }

            if (! Schema::hasColumn('rfq_items', 'description')) {
                $table->text('description')->nullable()->after('part_number');
            }

            if (! Schema::hasColumn('rfq_items', 'qty')) {
                $table->unsignedInteger('qty')->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('rfq_items', 'cad_doc_id')) {
                $table->foreignId('cad_doc_id')->nullable()->after('target_price')->constrained('documents')->nullOnDelete();
            }

            if (! Schema::hasColumn('rfq_items', 'specs_json')) {
                $table->json('specs_json')->nullable()->after('cad_doc_id');
            }

            if (! Schema::hasColumn('rfq_items', 'meta')) {
                $table->json('meta')->nullable()->after('specs_json');
            }

            if (! Schema::hasColumn('rfq_items', 'created_at')) {
                $table->timestamps();
            }

            if (! Schema::hasColumn('rfq_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        $partNumberExpression = $driver === 'sqlite'
            ? "COALESCE(part_name, 'LINE-' || line_no)"
            : "COALESCE(part_name, CONCAT('LINE-', line_no))";

        DB::statement(
            "UPDATE rfq_items SET part_number = {$partNumberExpression} WHERE part_number IS NULL"
        );

        DB::statement('UPDATE rfq_items SET description = spec WHERE description IS NULL AND spec IS NOT NULL');
        DB::statement('UPDATE rfq_items SET qty = quantity WHERE qty IS NULL AND quantity IS NOT NULL');

        DB::statement(
            'UPDATE rfq_items SET company_id = (SELECT company_id FROM rfqs WHERE rfqs.id = rfq_items.rfq_id) WHERE company_id IS NULL'
        );

        DB::statement(
            'UPDATE rfq_items SET created_by = (SELECT created_by FROM rfqs WHERE rfqs.id = rfq_items.rfq_id) WHERE created_by IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('rfq_items', function (Blueprint $table): void {
            foreach ([
                'company_id',
                'created_by',
                'updated_by',
                'cad_doc_id',
            ] as $foreignColumn) {
                if (Schema::hasColumn('rfq_items', $foreignColumn)) {
                    try {
                        $table->dropForeign([$foreignColumn]);
                    } catch (\Throwable) {
                        // noop
                    }
                }
            }

            foreach ([
                'company_id',
                'created_by',
                'updated_by',
                'part_number',
                'description',
                'qty',
                'cad_doc_id',
                'specs_json',
                'meta',
            ] as $column) {
                if (Schema::hasColumn('rfq_items', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('rfq_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('rfq_items', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
