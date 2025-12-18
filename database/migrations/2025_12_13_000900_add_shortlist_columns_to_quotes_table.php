<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotes', 'shortlisted_at')) {
                $table->timestamp('shortlisted_at')->nullable()->after('attachments_count');
            }

            if (! Schema::hasColumn('quotes', 'shortlisted_by')) {
                $table->foreignId('shortlisted_by')
                    ->nullable()
                    ->after('shortlisted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('quotes', 'shortlisted_by')) {
                $table->dropConstrainedForeignId('shortlisted_by');
            }

            if (Schema::hasColumn('quotes', 'shortlisted_at')) {
                $table->dropColumn('shortlisted_at');
            }
        });
    }
};
