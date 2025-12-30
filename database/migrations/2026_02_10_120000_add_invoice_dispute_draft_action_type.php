<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PREVIOUS_TYPES = [
        'rfq_draft',
        'supplier_message',
        'maintenance_checklist',
        'inventory_whatif',
        'item_draft',
        'invoice_draft',
        'approve_invoice',
        'receipt_draft',
        'invoice_match',
        'invoice_mismatch_resolution',
        'payment_draft',
        'supplier_onboard_draft',
    ];

    private const NEW_TYPES = [
        'rfq_draft',
        'supplier_message',
        'maintenance_checklist',
        'inventory_whatif',
        'item_draft',
        'invoice_draft',
        'approve_invoice',
        'receipt_draft',
        'invoice_match',
        'invoice_mismatch_resolution',
        'invoice_dispute_draft',
        'payment_draft',
        'supplier_onboard_draft',
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->setMysqlEnum(self::NEW_TYPES);

            return;
        }

        if ($driver === 'pgsql') {
            $this->setPostgresConstraint(self::NEW_TYPES);

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(self::NEW_TYPES);

            return;
        }

        Schema::table('ai_action_drafts', function (Blueprint $table): void {
            $table->enum('action_type', self::NEW_TYPES)->change();
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->setMysqlEnum(self::PREVIOUS_TYPES);

            return;
        }

        if ($driver === 'pgsql') {
            $this->setPostgresConstraint(self::PREVIOUS_TYPES);

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(self::PREVIOUS_TYPES);

            return;
        }

        Schema::table('ai_action_drafts', function (Blueprint $table): void {
            $table->enum('action_type', self::PREVIOUS_TYPES)->change();
        });
    }

    private function setMysqlEnum(array $values): void
    {
        $formatted = collect($values)
            ->map(static fn (string $value): string => sprintf("'%s'", $value))
            ->implode(',');

        DB::statement(sprintf(
            'ALTER TABLE ai_action_drafts MODIFY action_type ENUM(%s) NOT NULL',
            $formatted
        ));
    }

    private function setPostgresConstraint(array $values): void
    {
        $constraint = 'ai_action_drafts_action_type_check';
        $formatted = collect($values)
            ->map(static fn (string $value): string => sprintf("'%s'", $value))
            ->implode(', ');

        DB::statement(sprintf('ALTER TABLE ai_action_drafts DROP CONSTRAINT IF EXISTS %s', $constraint));
        DB::statement(sprintf(
            'ALTER TABLE ai_action_drafts ADD CONSTRAINT %s CHECK (action_type IN (%s))',
            $constraint,
            $formatted
        ));
    }

    private function rebuildSqliteTable(array $allowedValues): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::create('ai_action_drafts_tmp_v5', function (Blueprint $table) use ($allowedValues): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->enum('action_type', $allowedValues);
            $table->json('input_json');
            $table->json('output_json')->nullable();
            $table->json('citations_json')->nullable();
            $table->enum('status', ['drafted', 'approved', 'rejected', 'expired'])->default('drafted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'company_id',
                'status',
                'created_at',
            ], 'ai_action_drafts_tmp_v5_company_status_created_at_idx');

            $table->index([
                'entity_type',
                'entity_id',
            ], 'ai_action_drafts_tmp_v5_entity_type_entity_id_idx');
        });

        $columns = [
            'id',
            'company_id',
            'user_id',
            'action_type',
            'input_json',
            'output_json',
            'citations_json',
            'status',
            'approved_by',
            'approved_at',
            'rejected_reason',
            'entity_type',
            'entity_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $columnList = collect($columns)
            ->map(static fn (string $column): string => sprintf('"%s"', $column))
            ->implode(', ');

        DB::statement(sprintf(
            'INSERT INTO ai_action_drafts_tmp_v5 (%1$s) SELECT %1$s FROM ai_action_drafts',
            $columnList
        ));

        Schema::drop('ai_action_drafts');
        Schema::rename('ai_action_drafts_tmp_v5', 'ai_action_drafts');

        DB::statement('PRAGMA foreign_keys = ON;');
    }
};
