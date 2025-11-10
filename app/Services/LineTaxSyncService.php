<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class LineTaxSyncService
{
    /**
     * @param array<int, array{tax_code_id: int, rate_percent: float, amount_minor: int, sequence?: int}> $taxRows
     */
    public function sync(Model $taxable, int $companyId, array $taxRows): void
    {
        $relation = $taxable->taxes();

        $relation->delete();

        if ($taxRows === []) {
            return;
        }

        $index = 0;
        $payload = array_map(function (array $row) use ($companyId, &$index): array {
            $index++;

            return [
                'company_id' => $companyId,
                'tax_code_id' => (int) $row['tax_code_id'],
                'rate_percent' => (float) $row['rate_percent'],
                'amount_minor' => (int) $row['amount_minor'],
                'sequence' => $row['sequence'] ?? $index,
            ];
        }, $taxRows);

        $relation->createMany($payload);
    }
}
