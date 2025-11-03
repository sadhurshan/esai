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
        Schema::create('rfq_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->decimal('unit_price_usd', 12, 2);
            $table->unsignedSmallInteger('lead_time_days');
            $table->text('note')->nullable();
            $table->string('attachment_path')->nullable();
            $table->enum('via', ['direct', 'bidding'])->index();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();

            $table->index(['rfq_id']);
            $table->index(['supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_quotes');
    }
};
