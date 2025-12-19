<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_message_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('supplier_name')->nullable();
            $table->string('goal')->nullable();
            $table->string('tone')->nullable();
            $table->string('subject');
            $table->text('message_body');
            $table->json('negotiation_points_json')->nullable();
            $table->json('fallback_options_json')->nullable();
            $table->enum('status', ['draft', 'sent', 'archived'])->default('draft');
            $table->text('summary')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('needs_human_review')->default(false);
            $table->json('warnings_json')->nullable();
            $table->json('citations_json')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_message_drafts');
    }
};
