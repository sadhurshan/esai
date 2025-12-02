<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'number')) {
                $table->string('number', 40)->after('id');
            }

            if (! $this->ordersCompanyNumberIndexExists()) {
                $table->index(['company_id', 'number'], 'orders_company_id_number_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if ($this->ordersCompanyNumberIndexExists()) {
                $table->dropIndex('orders_company_id_number_index');
            }

            if (Schema::hasColumn('orders', 'number')) {
                $table->dropColumn('number');
            }
        });
    }

    private function ordersCompanyNumberIndexExists(): bool
    {
        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'orders';
        $index = 'orders_company_id_number_index';
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $results = $connection->select("PRAGMA index_list('$table')");

            foreach ($results as $result) {
                $name = property_exists($result, 'name') ? $result->name : ($result['name'] ?? null);

                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $results = $connection->select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

            return ! empty($results);
        }

        return false;
    }
};
