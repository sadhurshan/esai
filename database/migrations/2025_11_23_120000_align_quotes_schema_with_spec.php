<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotes', 'attachments_count')) {
                $table->unsignedInteger('attachments_count')->default(0)->after('total_minor');
            }

            if (Schema::hasColumn('quotes', 'note') && ! Schema::hasColumn('quotes', 'notes')) {
                $table->renameColumn('note', 'notes');
            }

            if (Schema::hasColumn('quotes', 'total') && ! Schema::hasColumn('quotes', 'total_price')) {
                $table->renameColumn('total', 'total_price');
            }

            if (Schema::hasColumn('quotes', 'total_minor') && ! Schema::hasColumn('quotes', 'total_price_minor')) {
                $table->renameColumn('total_minor', 'total_price_minor');
            }
        });

        DB::table('quotes')
            ->where('status', 'lost')
            ->update(['status' => 'rejected']);

        DB::table('quotes')
            ->where(static function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '');
            })
            ->update(['status' => 'draft']);

        if ($this->isMySql()) {
            DB::statement("ALTER TABLE quotes MODIFY status ENUM('draft','submitted','withdrawn','rejected','awarded') NOT NULL DEFAULT 'draft'");
        }
        if (! $this->isMySql()) {
            $this->syncSqliteEnumConstraint('quotes', 'status', ['draft', 'submitted', 'withdrawn', 'rejected', 'awarded']);
        }

        Schema::table('quote_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('quote_items', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('quote_id');
            }

            if (! Schema::hasColumn('quote_items', 'created_at')) {
                $table->timestamps();
            }

            if (! Schema::hasColumn('quote_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::statement('UPDATE quote_items SET company_id = (SELECT company_id FROM quotes WHERE quotes.id = quote_items.quote_id) WHERE company_id IS NULL');

        if ($this->isMySql()) {
            DB::statement('ALTER TABLE quote_items MODIFY company_id BIGINT UNSIGNED NOT NULL');
        }

        DB::table('quote_items')
            ->where('status', 'lost')
            ->update(['status' => 'rejected']);

        if ($this->isMySql()) {
            DB::statement("ALTER TABLE quote_items MODIFY status ENUM('pending','awarded','rejected') NOT NULL DEFAULT 'pending'");
        }
        if (! $this->isMySql()) {
            $this->syncSqliteEnumConstraint('quote_items', 'status', ['pending', 'awarded', 'rejected']);
        }

        Schema::table('quote_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('quote_items', 'company_id')) {
                return;
            }

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            $table->index('company_id', 'quote_items_company_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table): void {
            if (Schema::hasColumn('quote_items', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropIndex('quote_items_company_id_index');
                $table->dropColumn('company_id');
            }

            if (Schema::hasColumn('quote_items', 'created_at')) {
                $table->dropColumn(['created_at', 'updated_at']);
            }

            if (Schema::hasColumn('quote_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('quotes', 'attachments_count')) {
                $table->dropColumn('attachments_count');
            }

            if (Schema::hasColumn('quotes', 'notes') && ! Schema::hasColumn('quotes', 'note')) {
                $table->renameColumn('notes', 'note');
            }

            if (Schema::hasColumn('quotes', 'total_price')) {
                $table->renameColumn('total_price', 'total');
            }

            if (Schema::hasColumn('quotes', 'total_price_minor')) {
                $table->renameColumn('total_price_minor', 'total_minor');
            }
        });

        DB::table('quotes')
            ->where('status', 'rejected')
            ->update(['status' => 'lost']);

        if ($this->isMySql()) {
            DB::statement("ALTER TABLE quotes MODIFY status ENUM('draft','submitted','withdrawn','awarded','lost') NOT NULL DEFAULT 'submitted'");
        }
        if (! $this->isMySql()) {
            $this->syncSqliteEnumConstraint('quotes', 'status', ['draft', 'submitted', 'withdrawn', 'awarded', 'lost']);
        }

        DB::table('quote_items')
            ->where('status', 'rejected')
            ->update(['status' => 'lost']);

        if ($this->isMySql()) {
            DB::statement("ALTER TABLE quote_items MODIFY status ENUM('pending','awarded','lost') NOT NULL DEFAULT 'pending'");
        }
        if (! $this->isMySql()) {
            $this->syncSqliteEnumConstraint('quote_items', 'status', ['pending', 'awarded', 'lost']);
        }
    }

    private function syncSqliteEnumConstraint(string $table, string $column, array $allowedValues): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $definition = DB::table('sqlite_master')
            ->where('type', 'table')
            ->where('name', $table)
            ->value('sql');

        if (! is_string($definition) || trim($definition) === '') {
            return;
        }

        $pattern = sprintf("/\\(\\s*\"%s\"\\s+in\\s+\\((?:'[^']+'(?:\\s*,\\s*)?)+\\)\\s*\\)/i", preg_quote($column, '/'));
        $replacement = $this->buildSqliteCheckFragment($column, $allowedValues);
        $updatedSql = preg_replace($pattern, $replacement, $definition, 1, $count);

        if ($updatedSql === null || $count === 0) {
            return;
        }

        DB::statement('PRAGMA writable_schema = 1');
        DB::update("UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = ?", [$updatedSql, $table]);
        DB::statement('PRAGMA writable_schema = 0');
        DB::statement('VACUUM');
        DB::select('PRAGMA integrity_check');
    }

    private function buildSqliteCheckFragment(string $column, array $values): string
    {
        return sprintf('("%s" in (\'%s\'))', $column, implode("','", $values));
    }
};
