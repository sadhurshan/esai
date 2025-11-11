<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_locale_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->enum('number_format', ['system', 'de-DE', 'en-US', 'fr-FR', 'si-LK'])->default('system');
            $table->enum('date_format', ['system', 'ISO', 'DMY', 'MDY', 'YMD'])->default('system');
            $table->tinyInteger('first_day_of_week')->default(1);
            $table->json('weekend_days')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_locale_settings');
    }
};
