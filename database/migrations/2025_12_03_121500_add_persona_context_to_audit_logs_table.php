<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('persona_type', 32)->nullable()->after('user_id');
            $table->unsignedBigInteger('persona_company_id')->nullable()->after('persona_type');
            $table->unsignedBigInteger('acting_supplier_id')->nullable()->after('persona_company_id');

            $table->index(['persona_type', 'created_at'], 'audit_logs_persona_type_created_index');
            $table->index(['acting_supplier_id', 'created_at'], 'audit_logs_supplier_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_persona_type_created_index');
            $table->dropIndex('audit_logs_supplier_created_index');

            $table->dropColumn(['persona_type', 'persona_company_id', 'acting_supplier_id']);
        });
    }
};
