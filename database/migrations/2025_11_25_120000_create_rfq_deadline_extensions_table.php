<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rfq_deadline_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('extended_by')->constrained('users');
            $table->timestamp('previous_due_at')->nullable();
            $table->dateTime('new_due_at');
            $table->text('reason');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rfq_id', 'new_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_deadline_extensions');
    }
};
