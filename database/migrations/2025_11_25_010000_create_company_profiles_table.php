<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name', 191);
            $table->string('display_name', 191);
            $table->string('tax_id', 64)->nullable();
            $table->string('registration_number', 64)->nullable();
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
            $table->json('bill_to')->nullable();
            $table->json('ship_from')->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->string('mark_url', 2048)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('company_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
