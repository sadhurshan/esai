<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('po_change_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('changes_json');
            $table->string('reason', 255);
            $table->enum('status', ['proposed', 'accepted', 'rejected'])->default('proposed');
            $table->integer('po_revision_no')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id', 'status']);
            $table->index('proposed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_change_orders');
    }
};
