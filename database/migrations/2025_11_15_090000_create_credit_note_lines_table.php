<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained('invoice_lines')->cascadeOnDelete();
            $table->decimal('qty_to_credit', 12, 3)->default(0);
            $table->decimal('qty_invoiced', 12, 3)->default(0);
            $table->bigInteger('unit_price_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
            $table->char('currency', 3)->nullable();
            $table->string('uom', 16)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['credit_note_id', 'invoice_line_id'], 'credit_note_lines_unique');
            $table->index('invoice_line_id', 'credit_note_lines_invoice_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
    }
};
