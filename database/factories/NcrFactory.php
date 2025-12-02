<?php

namespace Database\Factories;

use App\Models\GoodsReceiptNote;
use App\Models\Ncr;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ncr>
 */
class NcrFactory extends Factory
{
    protected $model = Ncr::class;

    public function definition(): array
    {
        $note = GoodsReceiptNote::factory()->create();
        $poLine = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $note->purchase_order_id,
        ]);
        $user = User::factory()->create([
            'company_id' => $note->company_id,
        ]);

        return [
            'company_id' => $note->company_id,
            'goods_receipt_note_id' => $note->id,
            'purchase_order_line_id' => $poLine->id,
            'raised_by_id' => $user->id,
            'status' => 'open',
            'disposition' => 'rework',
            'reason' => $this->faker->sentence(8),
            'documents_json' => [],
        ];
    }
}
