<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_observations')) {
            Schema::table('pricing_observations', function (Blueprint $table): void {
                if (! $this->hasIndex('pricing_observations', 'pricing_observations_company_id_index')) {
                    $table->index('company_id');
                }

                if (! $this->hasIndex('pricing_observations', 'pricing_obs_company_observed_index')) {
                    $table->index(['company_id', 'observed_at'], 'pricing_obs_company_observed_index');
                }
            });

            $this->ensureMatchIndex();

            return;
        }

        Schema::create('pricing_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('rfq_item_id')->nullable()->constrained('rfq_items')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('quote_item_id')->nullable()->constrained('quote_items')->nullOnDelete();
            $table->unsignedInteger('revision_no')->nullable();
            $table->string('process')->nullable();
            $table->string('material')->nullable();
            $table->string('finish')->nullable();
            $table->string('region')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->char('currency', 3)->nullable();
            $table->bigInteger('unit_price_minor')->nullable();
            $table->enum('source_type', ['quote_item'])->default('quote_item');
            $table->timestamp('observed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index(['company_id', 'observed_at'], 'pricing_obs_company_observed_index');
        });

        $this->ensureMatchIndex();
    }

    private function hasIndex(string $table, string $index): bool
    {
        if ($this->isSqlite()) {
            $rows = DB::select("PRAGMA index_list('$table')");

            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index]
        );

        return $result !== null;
    }

    private function ensureMatchIndex(): void
    {
        if ($this->hasIndex('pricing_observations', 'pricing_obs_match_index')) {
            return;
        }

        if ($this->isSqlite()) {
            DB::statement(
                'create index if not exists pricing_obs_match_index on pricing_observations (company_id, process, material, finish, region)'
            );

            return;
        }

        DB::statement(
            'create index pricing_obs_match_index on pricing_observations (company_id, process(32), material(32), finish(32), region(32))'
        );
    }

    private function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_observations');
    }
};
