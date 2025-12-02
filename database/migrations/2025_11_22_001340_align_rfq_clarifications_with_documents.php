<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rfq_clarifications')) {
            return;
        }

        Schema::table('rfq_clarifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('rfq_clarifications', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('rfq_clarifications', 'attachments_json')) {
                $table->json('attachments_json')->nullable()->after('message');
            }

            if (! Schema::hasColumn('rfq_clarifications', 'version_increment')) {
                $table->boolean('version_increment')->default(false)->after('attachments_json');
            }

            if (! Schema::hasColumn('rfq_clarifications', 'version_no')) {
                $table->unsignedInteger('version_no')->nullable()->after('version_increment');
            }

            if (! Schema::hasColumn('rfq_clarifications', 'created_at')) {
                $table->timestamps();
            }

            if (! Schema::hasColumn('rfq_clarifications', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('rfq_clarifications', 'attachment_id')) {
            $clarifications = DB::table('rfq_clarifications')
                ->select('id', 'attachment_id')
                ->whereNotNull('attachment_id')
                ->get();

            foreach ($clarifications as $clarification) {
                DB::table('rfq_clarifications')
                    ->where('id', $clarification->id)
                    ->update([
                        'attachments_json' => json_encode([
                            [
                                'document_id' => (int) $clarification->attachment_id,
                            ],
                        ]),
                    ]);
            }

            Schema::table('rfq_clarifications', function (Blueprint $table): void {
                $table->dropColumn('attachment_id');
            });
        }

        if (! $this->indexExists('rfq_clarifications', 'rfq_clarifications_rfq_created_at_index')) {
            Schema::table('rfq_clarifications', function (Blueprint $table): void {
                $table->index(['rfq_id', 'created_at'], 'rfq_clarifications_rfq_created_at_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rfq_clarifications')) {
            return;
        }

        if (! Schema::hasColumn('rfq_clarifications', 'attachment_id')) {
            Schema::table('rfq_clarifications', function (Blueprint $table): void {
                $table->unsignedBigInteger('attachment_id')->nullable()->after('message');
            });

            $clarifications = DB::table('rfq_clarifications')
                ->select('id', 'attachments_json')
                ->whereNotNull('attachments_json')
                ->get();

            foreach ($clarifications as $clarification) {
                $payload = json_decode((string) $clarification->attachments_json, true);
                $documentId = null;

                if (is_array($payload)) {
                    $first = $payload[0] ?? null;

                    if (is_array($first) && isset($first['document_id']) && is_numeric($first['document_id'])) {
                        $documentId = (int) $first['document_id'];
                    } elseif (is_numeric($first)) {
                        $documentId = (int) $first;
                    }
                }

                if ($documentId !== null) {
                    DB::table('rfq_clarifications')
                        ->where('id', $clarification->id)
                        ->update(['attachment_id' => $documentId]);
                }
            }
        }

        if ($this->indexExists('rfq_clarifications', 'rfq_clarifications_rfq_created_at_index')) {
            Schema::table('rfq_clarifications', function (Blueprint $table): void {
                $table->dropIndex('rfq_clarifications_rfq_created_at_index');
            });
        }

        Schema::table('rfq_clarifications', function (Blueprint $table): void {
            if (Schema::hasColumn('rfq_clarifications', 'attachments_json')) {
                $table->dropColumn('attachments_json');
            }

            if (Schema::hasColumn('rfq_clarifications', 'version_increment')) {
                $table->dropColumn('version_increment');
            }

            if (Schema::hasColumn('rfq_clarifications', 'version_no')) {
                $table->dropColumn('version_no');
            }

            if (Schema::hasColumn('rfq_clarifications', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('rfq_clarifications', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection()->getDriverName();

        return match ($connection) {
            'sqlite' => $this->sqliteIndexExists($table, $index),
            'mysql', 'mariadb' => $this->mysqlIndexExists($table, $index),
            'pgsql' => $this->postgresIndexExists($table, $index),
            default => false,
        };
    }

    private function sqliteIndexExists(string $table, string $index): bool
    {
        $rows = DB::select("PRAGMA index_list('".$table."')");

        foreach ($rows as $row) {
            if ((string) $row->name === $index) {
                return true;
            }
        }

        return false;
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $count = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->count();

        return $count > 0;
    }

    private function postgresIndexExists(string $table, string $index): bool
    {
        $count = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->count();

        return $count > 0;
    }
};
