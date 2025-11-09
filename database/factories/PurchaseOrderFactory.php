<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'po_number' => 'PO-' . Str::upper(Str::random(8)),
            'currency' => 'USD',
            'status' => 'draft',
            'revision_no' => 0,
        ];
    }
}
