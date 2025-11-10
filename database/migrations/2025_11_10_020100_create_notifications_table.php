<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', [
                'rfq_created',
                'quote_submitted',
                'po_issued',
                'grn_posted',
                'invoice_created',
                'invoice_status_changed',
                'rfq.clarification.question',
                'rfq.clarification.answer',
                'rfq.clarification.amendment',
                'plan_overlimit',
                'certificate_expiry',
                'analytics_query',
                'approvals.pending',
                'rma.raised',
                'rma.reviewed',
                'rma.closed',
            ]);
            $table->string('title', 191);
            $table->text('body');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('channel', ['push', 'email', 'both'])->default('both');
            $table->timestamp('read_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'read_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
