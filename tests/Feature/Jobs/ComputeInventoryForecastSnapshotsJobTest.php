<?php

use App\Enums\InventoryPolicy;
use App\Enums\InventoryTxnType;
use App\Jobs\ComputeInventoryForecastSnapshotsJob;
use App\Models\Bin;
use App\Models\Company;
use App\Models\ForecastSnapshot;
use App\Models\Inventory;
use App\Models\InventorySetting;
use App\Models\InventoryTxn;
use App\Models\Part;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('aggregates inventory metrics into forecast snapshots', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 00:00:00'));

    $company = Company::factory()->create();
    $part = Part::factory()->create([
        'company_id' => $company->id,
    ]);

    $warehouse = Warehouse::factory()->create([
        'company_id' => $company->id,
    ]);

    $bin = Bin::factory()->create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
    ]);

    Inventory::create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'bin_id' => $bin->id,
        'on_hand' => 150,
        'allocated' => 0,
        'on_order' => 40,
        'uom' => 'pcs',
    ]);

    InventorySetting::create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'safety_stock' => 30,
        'min_qty' => null,
        'max_qty' => null,
        'reorder_qty' => null,
        'lead_time_days' => 10,
        'lot_size' => null,
        'policy' => InventoryPolicy::ForecastDriven,
    ]);

    $issueTxn = InventoryTxn::create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'bin_id' => $bin->id,
        'type' => InventoryTxnType::Issue,
        'qty' => 120,
        'uom' => 'pcs',
        'ref_type' => null,
        'ref_id' => null,
        'note' => null,
        'performed_by' => null,
    ]);
    $issueTxn->forceFill([
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ])->save();

    $adjustTxn = InventoryTxn::create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'bin_id' => $bin->id,
        'type' => InventoryTxnType::AdjustOut,
        'qty' => 60,
        'uom' => 'pcs',
        'ref_type' => null,
        'ref_id' => null,
        'note' => null,
        'performed_by' => null,
    ]);
    $adjustTxn->forceFill([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ])->save();

    app(ComputeInventoryForecastSnapshotsJob::class)->handle();

    $snapshot = ForecastSnapshot::first();
    expect($snapshot)->not()->toBeNull();

    expect($snapshot->company_id)->toBe($company->id);
    expect($snapshot->part_id)->toBe($part->id);
    expect($snapshot->period_start->toDateString())->toBe('2025-01-10');
    expect($snapshot->period_end->toDateString())->toBe('2025-02-09');
    expect($snapshot->horizon_days)->toBe(30);

    expect((float) $snapshot->avg_daily_demand)->toBe(2.0);
    expect((float) $snapshot->demand_qty)->toBe(60.0);
    expect((float) $snapshot->on_hand_qty)->toBe(150.0);
    expect((float) $snapshot->on_order_qty)->toBe(40.0);
    expect((float) $snapshot->safety_stock_qty)->toBe(30.0);
    expect((float) $snapshot->projected_runout_days)->toBe(80.0);
});
