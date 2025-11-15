<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('summary', 255);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_type', 40)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'event_type'], 'po_events_type_index');
            $table->index('occurred_at', 'po_events_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_events');
    }
};
