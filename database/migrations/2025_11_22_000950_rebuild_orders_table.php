<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('so_number', 50)->unique();
            $table->enum('status', ['pending', 'in_production', 'in_transit', 'delivered', 'cancelled'])->index();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedInteger('ordered_qty')->default(0);
            $table->unsignedInteger('shipped_qty')->default(0);
            $table->json('timeline')->nullable();
            $table->json('shipping')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('ordered_at')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['supplier_company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->enum('party_type', ['supplier', 'customer'])->index();
            $table->string('party_name');
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->decimal('total_usd', 12, 2);
            $table->timestamp('ordered_at')->index();
            $table->enum('status', ['pending', 'confirmed', 'in_production', 'delivered', 'cancelled'])->index();
            $table->timestamps();
        });
    }
};
