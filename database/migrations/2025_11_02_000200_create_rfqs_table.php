<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('item_name');
            $table->enum('type', ['ready_made', 'manufacture'])->index();
            $table->unsignedInteger('quantity');
            $table->string('material');
            $table->string('method');
            $table->string('tolerance')->nullable();
            $table->string('finish')->nullable();
            $table->string('client_company');
            $table->enum('status', ['awaiting', 'open', 'closed', 'awarded', 'cancelled'])->index();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->boolean('is_open_bidding')->default(false);
            $table->text('notes')->nullable();
            $table->string('cad_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfqs');
    }
};
