<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotes', 'subtotal')) {
                $table->decimal('subtotal', 14, 2)->default(0)->after('unit_price');
            }

            if (! Schema::hasColumn('quotes', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('quotes', 'total')) {
                $table->decimal('total', 14, 2)->default(0)->after('tax_amount');
            }

            if (! Schema::hasColumn('quotes', 'subtotal_minor')) {
                $table->bigInteger('subtotal_minor')->nullable()->after('total');
            }

            if (! Schema::hasColumn('quotes', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->nullable()->after('subtotal_minor');
            }

            if (! Schema::hasColumn('quotes', 'total_minor')) {
                $table->bigInteger('total_minor')->nullable()->after('tax_amount_minor');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'subtotal')) {
                $table->decimal('subtotal', 14, 2)->default(0)->after('tax_percent');
            }

            if (! Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('purchase_orders', 'total')) {
                $table->decimal('total', 14, 2)->default(0)->after('tax_amount');
            }

            if (! Schema::hasColumn('purchase_orders', 'subtotal_minor')) {
                $table->bigInteger('subtotal_minor')->nullable()->after('total');
            }

            if (! Schema::hasColumn('purchase_orders', 'tax_amount_minor')) {
                $table->bigInteger('tax_amount_minor')->nullable()->after('subtotal_minor');
            }

            if (! Schema::hasColumn('purchase_orders', 'total_minor')) {
                $table->bigInteger('total_minor')->nullable()->after('tax_amount_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_orders', 'total_minor')) {
                $table->dropColumn('total_minor');
            }

            if (Schema::hasColumn('purchase_orders', 'tax_amount_minor')) {
                $table->dropColumn('tax_amount_minor');
            }

            if (Schema::hasColumn('purchase_orders', 'subtotal_minor')) {
                $table->dropColumn('subtotal_minor');
            }

            if (Schema::hasColumn('purchase_orders', 'total')) {
                $table->dropColumn('total');
            }

            if (Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }

            if (Schema::hasColumn('purchase_orders', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });

        Schema::table('quotes', function (Blueprint $table): void {
            if (Schema::hasColumn('quotes', 'total_minor')) {
                $table->dropColumn('total_minor');
            }

            if (Schema::hasColumn('quotes', 'tax_amount_minor')) {
                $table->dropColumn('tax_amount_minor');
            }

            if (Schema::hasColumn('quotes', 'subtotal_minor')) {
                $table->dropColumn('subtotal_minor');
            }

            if (Schema::hasColumn('quotes', 'total')) {
                $table->dropColumn('total');
            }

            if (Schema::hasColumn('quotes', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }

            if (Schema::hasColumn('quotes', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }
};
