<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->unsignedTinyInteger('rating');
            $table->json('capabilities');
            $table->json('materials');
            $table->string('location_region')->index();
            $table->unsignedInteger('min_order_qty');
            $table->unsignedSmallInteger('avg_response_hours');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
