<?php

use App\Enums\DigitalTwinAssetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_twin_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('digital_twin_id')->constrained('digital_twins')->cascadeOnDelete();
            $table->string('type', 16)->default(DigitalTwinAssetType::OTHER->value);
            $table->string('disk', 32)->default('s3');
            $table->string('path');
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 96)->nullable();
            $table->string('mime', 128);
            $table->boolean('is_primary')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_twin_assets');
    }
};
