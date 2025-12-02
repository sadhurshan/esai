<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoodsReceiptNote>
 */
class GoodsReceiptNoteFactory extends Factory
{
    protected $model = GoodsReceiptNote::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'number' => 'GRN-'.Str::upper(Str::random(6)),
            'inspected_by_id' => User::factory(),
            'inspected_at' => now(),
            'status' => 'pending',
            'reference' => $this->faker->optional()->bothify('ASN-####'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GoodsReceiptNote $note): void {
            if ($note->purchase_order_id !== null) {
                $purchaseOrder = PurchaseOrder::query()->find($note->purchase_order_id);
                if ($purchaseOrder !== null) {
                    $note->company_id = $purchaseOrder->company_id;
                }
            }

            if ($note->inspected_by_id !== null && $note->company_id !== null) {
                User::query()->whereKey($note->inspected_by_id)->update([
                    'company_id' => $note->company_id,
                ]);
            }
        })->afterCreating(function (GoodsReceiptNote $note): void {
            if ($note->purchase_order_id !== null) {
                $purchaseOrder = PurchaseOrder::query()->find($note->purchase_order_id);
                if ($purchaseOrder !== null && (int) $note->company_id !== (int) $purchaseOrder->company_id) {
                    $note->company_id = $purchaseOrder->company_id;
                    $note->save();
                }
            }
        });
    }
}
