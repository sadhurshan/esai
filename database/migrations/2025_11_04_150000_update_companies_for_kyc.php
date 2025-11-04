<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('registration_no', 120)->nullable()->after('slug');
            $table->string('tax_id', 120)->nullable()->after('registration_no');
            $table->char('country', 2)->nullable()->after('tax_id');
            $table->string('email_domain', 191)->nullable()->after('country');
            $table->string('primary_contact_name', 160)->nullable()->after('email_domain');
            $table->string('primary_contact_email', 191)->nullable()->after('primary_contact_name');
            $table->string('primary_contact_phone', 60)->nullable()->after('primary_contact_email');
            $table->text('address')->nullable()->after('primary_contact_phone');
            $table->string('phone', 60)->nullable()->after('address');
            $table->string('website', 191)->nullable()->after('phone');
            $table->text('rejection_reason')->nullable()->after('trial_ends_at');
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending'");
        }

        Schema::create('company_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('type', ['registration', 'tax', 'esg', 'other']);
            $table->string('path');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE companies MODIFY COLUMN status ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'registration_no',
                'tax_id',
                'country',
                'email_domain',
                'primary_contact_name',
                'primary_contact_email',
                'primary_contact_phone',
                'address',
                'phone',
                'website',
                'rejection_reason',
            ]);
        });
    }
};
