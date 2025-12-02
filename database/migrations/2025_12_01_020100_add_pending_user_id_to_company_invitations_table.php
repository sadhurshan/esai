<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_invitations', 'pending_user_id')) {
                $table->foreignId('pending_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('company_invitations', 'pending_user_id')) {
                $table->dropConstrainedForeignId('pending_user_id');
            }
        });
    }
};
