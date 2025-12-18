<?php

namespace App\Console\Commands;

use App\Support\Schema\DatabaseInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'schema:align', description: 'Report schema drift against section 26 spec without mutating data')]
class SchemaAlign extends Command
{
    protected $signature = 'schema:align {--format=table : Output format: table or json}';

    public function __construct(private DatabaseInspector $inspector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        $results = [];

        foreach ($this->schemaSpec() as $table => $definition) {
            $results[] = $this->inspectTable($table, $definition);
        }

        if ($format === 'json') {
            $this->output->writeln(json_encode($results, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line(sprintf('Connection: %s', $this->inspector->connection()));

        $rows = array_map(function (array $result) {
            return [
                $result['table'],
                $result['status'],
                implode(', ', $result['missing_columns']),
                implode(', ', $result['missing_indexes']),
                implode(', ', $result['skipped_indexes']),
            ];
        }, $results);

        $this->table([
            'Table',
            'Status',
            'Missing Columns',
            'Missing Indexes',
            'Skipped Indexes',
        ], $rows);

        $hasFailures = collect($results)
            ->contains(fn ($item) => in_array($item['status'], ['missing', 'mismatch'], true));

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{table: string, status: string, missing_columns: array<int, string>, missing_indexes: array<int, string>, skipped_indexes: array<int, string>}
     */
    protected function inspectTable(string $table, array $definition): array
    {
        if (! $this->inspector->hasTable($table)) {
            return [
                'table' => $table,
                'status' => 'missing',
                'missing_columns' => $definition['columns'] ?? [],
                'missing_indexes' => [],
                'skipped_indexes' => [],
            ];
        }

        $missingColumns = [];

        foreach ($definition['columns'] ?? [] as $column) {
            if (! $this->inspector->columnExists($table, $column)) {
                $missingColumns[] = $column;
            }
        }

        $missingIndexes = [];
        $skippedIndexes = [];

        foreach ($definition['indexes'] ?? [] as $index) {
            $type = strtolower((string) ($index['type'] ?? 'index'));
            $columns = Arr::wrap($index['columns'] ?? []);

            if ($type === 'fulltext' && ! in_array($this->inspector->connection(), ['mysql', 'mariadb'], true)) {
                $skippedIndexes[] = implode(', ', $columns).' (fulltext)';
                continue;
            }

            if ($columns === []) {
                continue;
            }

            if (! $this->inspector->hasIndex($table, $columns, $type)) {
                $missingIndexes[] = implode(', ', $columns).' ('.$type.')';
            }
        }

        $status = empty($missingColumns) && empty($missingIndexes) ? 'ok' : 'mismatch';

        return [
            'table' => $table,
            'status' => $status,
            'missing_columns' => $missingColumns,
            'missing_indexes' => $missingIndexes,
            'skipped_indexes' => $skippedIndexes,
        ];
    }

    /**
     * @return array<string, array{columns: array<int, string>, indexes?: array<int, array{columns: array<int, string>, type?: string}>}>
     */
    protected function schemaSpec(): array
    {
        return [
            'companies' => [
                'columns' => [
                    'id',
                    'name',
                    'slug',
                    'status',
                    'region',
                    'owner_user_id',
                    'rfqs_monthly_used',
                    'storage_used_mb',
                    'stripe_id',
                    'plan_code',
                    'trial_ends_at',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['slug'], 'type' => 'unique'],
                    ['columns' => ['status']],
                    ['columns' => ['plan_code']],
                    ['columns' => ['owner_user_id']],
                ],
            ],
            'users' => [
                'columns' => [
                    'id',
                    'company_id',
                    'name',
                    'email',
                    'email_verified_at',
                    'password',
                    'remember_token',
                    'role',
                    'last_login_at',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['email'], 'type' => 'unique'],
                    ['columns' => ['company_id', 'role']],
                ],
            ],
            'company_user' => [
                'columns' => [
                    'id',
                    'company_id',
                    'user_id',
                    'role',
                    'created_at',
                    'updated_at',
                ],
                'indexes' => [
                    ['columns' => ['company_id', 'user_id'], 'type' => 'unique'],
                ],
            ],
            'customers' => [
                'columns' => [
                    'id',
                    'company_id',
                    'name',
                    'email',
                    'stripe_id',
                    'pm_type',
                    'pm_last_four',
                    'default_payment_method',
                    'created_at',
                    'updated_at',
                ],
                'indexes' => [
                    ['columns' => ['stripe_id'], 'type' => 'unique'],
                    ['columns' => ['company_id']],
                ],
            ],
            'subscriptions' => [
                'columns' => [
                    'id',
                    'company_id',
                    'customer_id',
                    'name',
                    'stripe_id',
                    'stripe_status',
                    'stripe_plan',
                    'quantity',
                    'trial_ends_at',
                    'ends_at',
                    'created_at',
                    'updated_at',
                ],
                'indexes' => [
                    ['columns' => ['stripe_id'], 'type' => 'unique'],
                    ['columns' => ['company_id', 'stripe_status']],
                    ['columns' => ['customer_id']],
                ],
            ],
            'subscription_items' => [
                'columns' => [
                    'id',
                    'subscription_id',
                    'stripe_id',
                    'stripe_product',
                    'stripe_price',
                    'quantity',
                    'created_at',
                    'updated_at',
                ],
                'indexes' => [
                    ['columns' => ['stripe_id'], 'type' => 'unique'],
                    ['columns' => ['subscription_id']],
                ],
            ],
            'plans' => [
                'columns' => [
                    'id',
                    'code',
                    'name',
                    'price_usd',
                    'rfqs_per_month',
                    'users_max',
                    'storage_gb',
                    'erp_integrations_max',
                    'created_at',
                    'updated_at',
                ],
                'indexes' => [
                    ['columns' => ['code'], 'type' => 'unique'],
                ],
            ],
            'suppliers' => [
                'columns' => [
                    'id',
                    'company_id',
                    'name',
                    'country',
                    'city',
                    'email',
                    'phone',
                    'website',
                    'status',
                    'capabilities',
                    'rating_avg',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['company_id', 'status']],
                    ['columns' => $this->supplierFulltextColumns(), 'type' => 'fulltext'],
                ],
            ],
            'supplier_documents' => [
                'columns' => [
                    'id',
                    'supplier_id',
                    'company_id',
                    'type',
                    'path',
                    'mime',
                    'size_bytes',
                    'issued_at',
                    'expires_at',
                    'status',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['supplier_id', 'type']],
                    ['columns' => ['type', 'expires_at']],
                ],
            ],
            'rfqs' => [
                'columns' => [
                    'id',
                    'company_id',
                    'created_by',
                    'title',
                    'type',
                    'material',
                    'method',
                    'tolerance_finish',
                    'incoterm',
                    'currency',
                    'open_bidding',
                    'publish_at',
                    'due_at',
                    'close_at',
                    'status',
                    'version',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['company_id', 'status', 'due_at']],
                    ['columns' => ['title'], 'type' => 'fulltext'],
                ],
            ],
            'rfq_items' => [
                'columns' => [
                    'id',
                    'rfq_id',
                    'line_no',
                    'part_name',
                    'spec',
                    'quantity',
                    'uom',
                    'target_price',
                ],
                'indexes' => [
                    ['columns' => ['rfq_id']],
                    ['columns' => ['rfq_id', 'line_no'], 'type' => 'unique'],
                ],
            ],
            'rfq_invitations' => [
                'columns' => [
                    'id',
                    'company_id',
                    'rfq_id',
                    'supplier_id',
                    'invited_by',
                    'status',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['company_id']],
                    ['columns' => ['rfq_id', 'supplier_id', 'deleted_at'], 'type' => 'unique'],
                ],
            ],
            'rfq_clarifications' => [
                'columns' => [
                    'id',
                    'company_id',
                    'rfq_id',
                    'user_id',
                    'type',
                    'message',
                    'attachments_json',
                    'version_increment',
                    'version_no',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['rfq_id', 'created_at']],
                ],
            ],
            'quotes' => [
                'columns' => [
                    'id',
                    'company_id',
                    'rfq_id',
                    'supplier_id',
                    'submitted_by',
                    'currency',
                    'unit_price',
                    'min_order_qty',
                    'lead_time_days',
                    'notes',
                    'status',
                    'revision_no',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'indexes' => [
                    ['columns' => ['rfq_id', 'supplier_id', 'revision_no'], 'type' => 'unique'],
                    ['columns' => ['rfq_id', 'supplier_id', 'status']],
                ],
            ],
            'quote_items' => [
                'columns' => [
                    'id',
                    'quote_id',
                    'rfq_item_id',
                    'unit_price',
                    'lead_time_days',
                    'note',
                ],
                'indexes' => [
                    ['columns' => ['quote_id']],
                    ['columns' => ['quote_id', 'rfq_item_id'], 'type' => 'unique'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function supplierFulltextColumns(): array
    {
        if (in_array($this->inspector->connection(), ['mysql', 'mariadb'], true)) {
            return ['name', 'capabilities_search'];
        }

        return ['name', 'capabilities'];
    }
}
