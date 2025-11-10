<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('base_code', 3);
            $table->char('quote_code', 3);
            $table->decimal('rate', 18, 8);
            $table->date('as_of');
            $table->timestamps();

            $table->unique(['base_code', 'quote_code', 'as_of'], 'fx_rates_base_quote_date_unique');
            $table->index('as_of');
            $table->foreign('base_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->foreign('quote_code')->references('code')->on('currencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
