<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Order;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $statuses = ['pending', 'in_production', 'in_transit', 'delivered', 'cancelled'];
        $companyFactory = Company::factory();
        $orderedQty = $this->faker->numberBetween(10, 500);
        $shippedQty = $this->faker->numberBetween(0, $orderedQty);

        return [
            'company_id' => $companyFactory,
            'purchase_order_id' => PurchaseOrder::factory()
                ->for($companyFactory, 'company')
                ->state([
                    'po_number' => 'PO-'.Str::upper(Str::random(8)),
                ]),
            'number' => 'PO-'.str_pad((string) $this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'supplier_company_id' => Company::factory(),
            'so_number' => 'SO-'.Str::upper(Str::random(10)),
            'status' => $this->faker->randomElement($statuses),
            'currency' => 'USD',
            'total_minor' => $this->faker->numberBetween(50_00, 250_000_00),
            'ordered_qty' => $orderedQty,
            'shipped_qty' => $shippedQty,
            'timeline' => [
                ['type' => 'status_change', 'status' => 'pending', 'occurred_at' => now()->subDays(5)->toIso8601String()],
            ],
            'shipping' => [
                'incoterm' => 'FOB',
                'ship_to' => $this->faker->city(),
            ],
            'metadata' => [
                'rfq_id' => $this->faker->randomNumber(5),
            ],
            'ordered_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'acknowledged_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'delivered_at' => $this->faker->optional()->dateTimeBetween('-10 days', 'now'),
        ];
    }
}
