<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_money_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('base_currency', 3);
            $table->char('pricing_currency', 3)->nullable();
            $table->enum('fx_source', ['manual', 'provider'])->default('manual');
            $table->enum('price_round_rule', ['bankers', 'half_up'])->default('half_up');
            $table->enum('tax_regime', ['exclusive', 'inclusive'])->default('exclusive');
            $table->json('defaults_meta')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->foreign('base_currency')->references('code')->on('currencies')->cascadeOnDelete();
            $table->foreign('pricing_currency')->references('code')->on('currencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_money_settings');
    }
};
