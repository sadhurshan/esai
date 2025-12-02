<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (Schema::hasColumn('rfqs', 'cad_document_id')) {
                return;
            }

            $definition = $table->foreignId('cad_document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();

            if (Schema::hasColumn('rfqs', 'cad_path')) {
                $definition->after('cad_path');
            } elseif (Schema::hasColumn('rfqs', 'attachments_count')) {
                $definition->after('attachments_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfqs', 'cad_document_id')) {
                return;
            }

            $table->dropConstrainedForeignId('cad_document_id');
        });
    }
};
