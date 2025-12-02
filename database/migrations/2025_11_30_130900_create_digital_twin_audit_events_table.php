<?php

use App\Enums\DigitalTwinAuditEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_twin_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('digital_twin_id')->constrained('digital_twins')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32)->default(DigitalTwinAuditEvent::Created->value);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event');
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_twin_audit_events');
    }
};
