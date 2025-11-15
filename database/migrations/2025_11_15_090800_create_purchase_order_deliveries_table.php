<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('channel', ['email', 'webhook']);
            $table->json('recipients_to')->nullable();
            $table->json('recipients_cc')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('sent');
            $table->string('delivery_reference', 120)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('response_meta')->nullable();
            $table->text('error_reason')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'channel'], 'po_deliveries_channel_index');
            $table->index(['purchase_order_id', 'status'], 'po_deliveries_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_deliveries');
    }
};
