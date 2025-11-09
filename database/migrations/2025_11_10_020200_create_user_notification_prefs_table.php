<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_prefs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', [
                'rfq_created',
                'quote_submitted',
                'po_issued',
                'grn_posted',
                'invoice_created',
                'invoice_status_changed',
                'plan_overlimit',
                'certificate_expiry',
            ]);
            $table->enum('channel', ['push', 'email', 'both'])->default('both');
            $table->enum('digest', ['none', 'daily', 'weekly'])->default('none');
            $table->timestamps();

            $table->unique(['user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_prefs');
    }
};
