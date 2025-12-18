<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_EVENT_TYPES = [
        'rfq_created',
        'quote_submitted',
        'po_issued',
        'grn_posted',
        'invoice_created',
        'invoice_status_changed',
        'rfq.clarification.question',
        'rfq.clarification.answer',
        'rfq.clarification.amendment',
        'rfq.deadline.extended',
        'quote.revision.submitted',
        'quote.withdrawn',
        'rfq_line_awarded',
        'rfq_line_lost',
        'plan_overlimit',
        'certificate_expiry',
        'analytics_query',
        'approvals.pending',
        'rma.raised',
        'rma.reviewed',
        'rma.closed',
        'maintenance_completed',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications_tmp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 191);
            $table->string('title', 191);
            $table->text('body');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('channel', ['push', 'email', 'both'])->default('both');
            $table->timestamp('read_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('notifications')->orderBy('id')->chunkById(500, function ($rows): void {
            $payload = [];

            foreach ($rows as $row) {
                $payload[] = [
                    'id' => $row->id,
                    'company_id' => $row->company_id,
                    'user_id' => $row->user_id,
                    'event_type' => $row->event_type,
                    'title' => $row->title,
                    'body' => $row->body,
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'channel' => $row->channel,
                    'read_at' => $row->read_at,
                    'meta' => $row->meta,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ];
            }

            if ($payload !== []) {
                DB::table('notifications_tmp')->insert($payload);
            }
        });

        Schema::drop('notifications');
        Schema::rename('notifications_tmp', 'notifications');

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'read_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications_tmp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', self::LEGACY_EVENT_TYPES);
            $table->string('title', 191);
            $table->text('body');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('channel', ['push', 'email', 'both'])->default('both');
            $table->timestamp('read_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('notifications')->orderBy('id')->chunkById(500, function ($rows): void {
            $payload = [];

            foreach ($rows as $row) {
                $eventType = in_array($row->event_type, self::LEGACY_EVENT_TYPES, true)
                    ? $row->event_type
                    : 'rfq_created';

                $payload[] = [
                    'id' => $row->id,
                    'company_id' => $row->company_id,
                    'user_id' => $row->user_id,
                    'event_type' => $eventType,
                    'title' => $row->title,
                    'body' => $row->body,
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'channel' => $row->channel,
                    'read_at' => $row->read_at,
                    'meta' => $row->meta,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ];
            }

            if ($payload !== []) {
                DB::table('notifications_tmp')->insert($payload);
            }
        });

        Schema::drop('notifications');
        Schema::rename('notifications_tmp', 'notifications');

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'read_at']);
            $table->index('event_type');
        });
    }
};
