<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_documents', 'document_id')) {
                $table->foreignId('document_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('documents')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('company_documents', 'document_id')) {
                $table->dropConstrainedForeignId('document_id');
            }
        });
    }
};
