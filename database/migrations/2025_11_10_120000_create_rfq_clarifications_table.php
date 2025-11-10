<?php

use App\Enums\RfqClarificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rfq_clarifications');

        Schema::create('rfq_clarifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', RfqClarificationType::values());
            $table->text('message');
            $table->json('attachments_json')->nullable();
            $table->boolean('version_increment')->default(false);
            $table->unsignedInteger('version_no')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rfq_id', 'created_at'], 'rfq_clarifications_rfq_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_clarifications');
    }
};
