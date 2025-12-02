<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_twin_specs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('digital_twin_id')->constrained('digital_twins')->cascadeOnDelete();
            $table->string('name');
            $table->string('value', 512);
            $table->string('uom', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['digital_twin_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_twin_specs');
    }
};
