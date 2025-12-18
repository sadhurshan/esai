<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_VALUES = [
        'draft',
        'submitted',
        'buyer_review',
        'approved',
        'rejected',
        'paid',
    ];

    private const MATCH_VALUES = [
        'pending',
        'matched',
        'qty_mismatch',
        'price_mismatch',
        'unmatched',
    ];

    private const ATTACHMENT_KINDS = [
        'supporting',
        'credit',
        'evidence',
        'other',
    ];

    private const SCAN_STATES = [
        'pending',
        'passed',
        'failed',
    ];

    public function up(): void
    {
        $this->updateInvoicesTable();
        $this->updateInvoiceLines();
        $this->createInvoiceAttachments();

        $this->migrateStatusColumn();
        $this->backfillSupplierCompany();
        $this->backfillMoneyColumns();
        $this->backfillLineTotals();
        $this->initializeDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_attachments');

        Schema::table('invoice_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_lines', 'line_total_minor')) {
                $table->dropColumn('line_total_minor');
            }
        });

        $this->revertStatusColumn();

        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('invoices', 'supplier_company_id')) {
                $table->dropConstrainedForeignId('supplier_company_id');
            }

            foreach ([
                'due_date',
                'subtotal_minor',
                'tax_minor',
                'total_minor',
                'created_by_type',
                'created_by_id',
                'submitted_at',
                'reviewed_at',
                'reviewed_by_id',
                'review_note',
                'payment_reference',
                'matched_status',
            ] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    if (str_contains($column, '_id')) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }

    private function updateInvoicesTable(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'supplier_company_id')) {
                $table->foreignId('supplier_company_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('companies')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('invoice_date');
            }

            if (! Schema::hasColumn('invoices', 'subtotal_minor')) {
                $table->bigInteger('subtotal_minor')->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('invoices', 'tax_minor')) {
                $table->bigInteger('tax_minor')->default(0)->after('tax_amount');
            }

            if (! Schema::hasColumn('invoices', 'total_minor')) {
                $table->bigInteger('total_minor')->default(0)->after('total');
            }

            if (! Schema::hasColumn('invoices', 'created_by_type')) {
                $table->enum('created_by_type', ['buyer', 'supplier'])
                    ->default('buyer')
                    ->after('supplier_company_id');
            }

            if (! Schema::hasColumn('invoices', 'created_by_id')) {
                $table->foreignId('created_by_id')
                    ->nullable()
                    ->after('created_by_type')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'matched_status')) {
                $table->enum('matched_status', self::MATCH_VALUES)
                    ->default('pending')
                    ->after('status');
            }

            if (! Schema::hasColumn('invoices', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('matched_status');
            }

            if (! Schema::hasColumn('invoices', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('invoices', 'reviewed_by_id')) {
                $table->foreignId('reviewed_by_id')
                    ->nullable()
                    ->after('reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'review_note')) {
                $table->text('review_note')->nullable()->after('reviewed_by_id');
            }

            if (! Schema::hasColumn('invoices', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable()->after('review_note');
            }
        });
    }

    private function updateInvoiceLines(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_lines', 'line_total_minor')) {
                $table->bigInteger('line_total_minor')->nullable()->after('unit_price_minor');
            }
        });
    }

    private function createInvoiceAttachments(): void
    {
        if (Schema::hasTable('invoice_attachments')) {
            return;
        }

        Schema::create('invoice_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->enum('kind', self::ATTACHMENT_KINDS)->default('supporting');
            $table->json('metadata')->nullable();
            $table->enum('scan_status', self::SCAN_STATES)->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['invoice_id', 'document_id'], 'invoice_attachments_document_unique');
            $table->index(['company_id', 'kind'], 'invoice_attachments_company_kind_index');
        });
    }

    private function migrateStatusColumn(): void
    {
        if (! Schema::hasColumn('invoices', 'status')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $values = "'" . implode("','", self::STATUS_VALUES) . "'";
            DB::statement("ALTER TABLE invoices MODIFY status ENUM($values) NOT NULL DEFAULT 'draft'");
        } else {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('status')->default('draft')->change();
            });
        }
    }

    private function revertStatusColumn(): void
    {
        if (! Schema::hasColumn('invoices', 'status')) {
            return;
        }

        $driver = DB::getDriverName();
        $values = "'pending','paid','overdue','disputed'";

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE invoices MODIFY status ENUM($values) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    private function backfillSupplierCompany(): void
    {
        if (! Schema::hasColumn('invoices', 'supplier_company_id')) {
            return;
        }

        DB::table('invoices')
            ->select(['id', 'supplier_id'])
            ->whereNull('supplier_company_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if ($row->supplier_id === null) {
                        continue;
                    }

                    $supplierCompanyId = DB::table('suppliers')
                        ->where('id', $row->supplier_id)
                        ->value('company_id');

                    if ($supplierCompanyId === null) {
                        continue;
                    }

                    DB::table('invoices')
                        ->where('id', $row->id)
                        ->update(['supplier_company_id' => $supplierCompanyId]);
                }
            });
    }

    private function backfillMoneyColumns(): void
    {
        if (! Schema::hasColumn('invoices', 'subtotal_minor')) {
            return;
        }

        $expression = static fn (string $column) => DB::raw("ROUND(COALESCE($column, 0) * 100)");

        DB::table('invoices')
            ->whereNull('subtotal_minor')
            ->update(['subtotal_minor' => $expression('subtotal')]);

        DB::table('invoices')
            ->whereNull('tax_minor')
            ->update(['tax_minor' => $expression('tax_amount')]);

        DB::table('invoices')
            ->whereNull('total_minor')
            ->update(['total_minor' => $expression('total')]);
    }

    private function backfillLineTotals(): void
    {
        if (! Schema::hasColumn('invoice_lines', 'line_total_minor')) {
            return;
        }

        DB::table('invoice_lines')
            ->select(['id', 'quantity', 'unit_price_minor'])
            ->whereNull('line_total_minor')
            ->orderBy('id')
            ->chunkById(200, function ($lines): void {
                foreach ($lines as $line) {
                    if ($line->quantity === null || $line->unit_price_minor === null) {
                        continue;
                    }

                    DB::table('invoice_lines')
                        ->where('id', $line->id)
                        ->update([
                            'line_total_minor' => (int) $line->quantity * (int) $line->unit_price_minor,
                        ]);
                }
            });
    }

    private function initializeDefaults(): void
    {
        if (Schema::hasColumn('invoices', 'created_by_type')) {
            DB::table('invoices')
                ->whereNull('created_by_type')
                ->update(['created_by_type' => 'buyer']);
        }

        if (Schema::hasColumn('invoices', 'matched_status')) {
            DB::table('invoices')
                ->whereNull('matched_status')
                ->update(['matched_status' => 'pending']);
        }
    }
};
