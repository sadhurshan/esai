<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORIGINAL_TYPES = ['rfq_draft', 'supplier_message', 'maintenance_checklist', 'inventory_whatif'];
    private const UPDATED_TYPES = ['rfq_draft', 'supplier_message', 'maintenance_checklist', 'inventory_whatif', 'invoice_draft'];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->setMysqlEnum(self::UPDATED_TYPES);

            return;
        }

        if ($driver === 'pgsql') {
            $this->setPostgresConstraint(self::UPDATED_TYPES);

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(self::UPDATED_TYPES);

            return;
        }

        Schema::table('ai_action_drafts', function (Blueprint $table): void {
            $table->string('action_type', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->setMysqlEnum(self::ORIGINAL_TYPES);

            return;
        }

        if ($driver === 'pgsql') {
            $this->setPostgresConstraint(self::ORIGINAL_TYPES);

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(self::ORIGINAL_TYPES);

            return;
        }

        Schema::table('ai_action_drafts', function (Blueprint $table): void {
            $table->enum('action_type', self::ORIGINAL_TYPES)->change();
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

        Schema::create('ai_action_drafts_tmp', function (Blueprint $table) use ($allowedValues): void {
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

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
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
            'INSERT INTO ai_action_drafts_tmp (%1$s) SELECT %1$s FROM ai_action_drafts',
            $columnList
        ));

        Schema::drop('ai_action_drafts');
        Schema::rename('ai_action_drafts_tmp', 'ai_action_drafts');

        DB::statement('PRAGMA foreign_keys = ON;');
    }
};
