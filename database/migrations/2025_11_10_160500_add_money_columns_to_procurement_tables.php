<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rfq_items', 'currency')) {
            Schema::table('rfq_items', function (Blueprint $table): void {
                $table->char('currency', 3)->nullable()->after('target_price');
                $table->bigInteger('target_price_minor')->nullable()->after('currency');
                $table->foreign('currency')->references('code')->on('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('quote_items', 'currency')) {
            Schema::table('quote_items', function (Blueprint $table): void {
                $table->char('currency', 3)->nullable()->after('unit_price');
                $table->bigInteger('unit_price_minor')->nullable()->after('currency');
                $table->foreign('currency')->references('code')->on('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('po_lines', 'currency')) {
            Schema::table('po_lines', function (Blueprint $table): void {
                $table->char('currency', 3)->nullable()->after('unit_price');
                $table->bigInteger('unit_price_minor')->nullable()->after('currency');
                $table->foreign('currency')->references('code')->on('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('invoice_lines', 'currency')) {
            Schema::table('invoice_lines', function (Blueprint $table): void {
                $table->char('currency', 3)->nullable()->after('unit_price');
                $table->bigInteger('unit_price_minor')->nullable()->after('currency');
                $table->foreign('currency')->references('code')->on('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('credit_notes', 'amount_minor')) {
            Schema::table('credit_notes', function (Blueprint $table): void {
                if (! Schema::hasColumn('credit_notes', 'currency')) {
                    $table->char('currency', 3)->nullable()->after('credit_number');
                    $table->foreign('currency')->references('code')->on('currencies')->nullOnDelete();
                }

                $table->bigInteger('amount_minor')->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('credit_notes', 'amount_minor')) {
            Schema::table('credit_notes', function (Blueprint $table): void {
                $table->dropColumn('amount_minor');
            });
        }

        if (Schema::hasColumn('invoice_lines', 'unit_price_minor')) {
            if ($this->hasForeign('invoice_lines', 'invoice_lines_currency_foreign')) {
                Schema::table('invoice_lines', function (Blueprint $table): void {
                    $table->dropForeign('invoice_lines_currency_foreign');
                });
            }

            Schema::table('invoice_lines', function (Blueprint $table): void {
                $table->dropColumn(['currency', 'unit_price_minor']);
            });
        }

        if (Schema::hasColumn('po_lines', 'unit_price_minor')) {
            if ($this->hasForeign('po_lines', 'po_lines_currency_foreign')) {
                Schema::table('po_lines', function (Blueprint $table): void {
                    $table->dropForeign('po_lines_currency_foreign');
                });
            }

            Schema::table('po_lines', function (Blueprint $table): void {
                $table->dropColumn(['currency', 'unit_price_minor']);
            });
        }

        if (Schema::hasColumn('quote_items', 'unit_price_minor')) {
            if ($this->hasForeign('quote_items', 'quote_items_currency_foreign')) {
                Schema::table('quote_items', function (Blueprint $table): void {
                    $table->dropForeign('quote_items_currency_foreign');
                });
            }

            Schema::table('quote_items', function (Blueprint $table): void {
                $table->dropColumn(['currency', 'unit_price_minor']);
            });
        }

        if (Schema::hasColumn('rfq_items', 'target_price_minor')) {
            if ($this->hasForeign('rfq_items', 'rfq_items_currency_foreign')) {
                Schema::table('rfq_items', function (Blueprint $table): void {
                    $table->dropForeign('rfq_items_currency_foreign');
                });
            }

            Schema::table('rfq_items', function (Blueprint $table): void {
                $table->dropColumn(['currency', 'target_price_minor']);
            });
        }
    }

    private function hasForeign(string $table, string $key): bool
    {
        $connection = Schema::getConnection()->getDriverName();

        return match ($connection) {
            'sqlite' => false,
            'mysql', 'mariadb' => $this->mysqlForeignExists($table, $key),
            default => false,
        };
    }

    private function mysqlForeignExists(string $table, string $key): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return Schema::getConnection()->table('information_schema.key_column_usage')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $key)
            ->exists();
    }
};
