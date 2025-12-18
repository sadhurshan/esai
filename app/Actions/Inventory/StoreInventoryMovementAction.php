<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryTxnType;
use App\Models\Bin;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementLine;
use App\Models\InventoryTxn;
use App\Models\Part;
use App\Models\Warehouse;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreInventoryMovementAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{
     *     type: string,
     *     moved_at: string,
     *     notes?: string|null,
     *     reference?: array{source?: string|null, id?: string|null}|null,
     *     lines: array<int, array{
     *         item_id: int,
     *         qty: float,
     *         uom?: string|null,
     *         from_location_id?: int|null,
     *         to_location_id?: int|null,
     *         reason?: string|null,
     *     }>
     * }  $payload
     */
    public function execute(int $companyId, int $userId, array $payload): InventoryMovement
    {
        return DB::transaction(function () use ($companyId, $userId, $payload): InventoryMovement {
            $movement = InventoryMovement::query()->create([
                'company_id' => $companyId,
                'movement_number' => $this->generateMovementNumber($companyId),
                'type' => $payload['type'],
                'status' => 'posted',
                'moved_at' => CarbonImmutable::parse($payload['moved_at']),
                'reference_type' => Arr::get($payload, 'reference.source'),
                'reference_id' => Arr::get($payload, 'reference.id'),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($payload['lines'] as $index => $linePayload) {
                $this->storeLine($movement, $userId, $index, $linePayload);
            }

            $movement->load([
                'lines' => function ($query): void {
                    $query
                        ->orderBy('line_number')
                        ->with([
                            'part:id,company_id,part_number,name,uom',
                            'fromWarehouse:id,company_id,name,code',
                            'toWarehouse:id,company_id,name,code',
                            'fromBin:id,company_id,name,code,warehouse_id',
                            'fromBin.warehouse:id,company_id,name,code',
                            'toBin:id,company_id,name,code,warehouse_id',
                            'toBin.warehouse:id,company_id,name,code',
                        ]);
                },
                'creator:id,name',
            ]);

            $this->auditLogger->created($movement, [
                'movement_number' => $movement->movement_number,
                'type' => $movement->type,
                'moved_at' => $movement->moved_at?->toIso8601String(),
            ]);

            return $movement;
        });
    }

    private function storeLine(InventoryMovement $movement, int $userId, int $index, array $payload): void
    {
        $companyId = $movement->company_id;
        $lineKey = 'lines.' . $index;

        $part = Part::query()
            ->where('company_id', $companyId)
            ->whereKey($payload['item_id'])
            ->first();

        if ($part === null) {
            throw ValidationException::withMessages([
                $lineKey . '.item_id' => 'Select a valid inventory item.',
            ]);
        }

        $qty = (float) $payload['qty'];
        if ($qty <= 0) {
            throw ValidationException::withMessages([
                $lineKey . '.qty' => 'Quantity must be greater than zero.',
            ]);
        }

        $fromLocation = $this->resolveLocation($companyId, $payload['from_location_id'] ?? null, $lineKey . '.from_location_id');
        $toLocation = $this->resolveLocation($companyId, $payload['to_location_id'] ?? null, $lineKey . '.to_location_id');

        $uom = $payload['uom'] ?? $part->uom ?? 'EA';
        $reason = $payload['reason'] ?? null;
        $resultingOnHand = null;

        switch ($movement->type) {
            case 'receipt':
                $this->assertLocationRequired($toLocation, $lineKey . '.to_location_id', 'Select a destination location.');
                $resultingOnHand = $this->adjustInventory($companyId, $part->getKey(), $toLocation, $uom, $qty, $lineKey . '.qty');
                $this->recordTxn($movement, InventoryTxnType::Receive, $part->getKey(), $qty, $uom, $toLocation, $reason, $userId);
                $this->createLine($movement, $part->getKey(), $index + 1, $qty, $uom, $reason, null, $toLocation, $resultingOnHand);
                break;
            case 'issue':
                $this->assertLocationRequired($fromLocation, $lineKey . '.from_location_id', 'Select a source location.');
                $resultingOnHand = $this->adjustInventory($companyId, $part->getKey(), $fromLocation, $uom, -1 * $qty, $lineKey . '.qty');
                $this->recordTxn($movement, InventoryTxnType::Issue, $part->getKey(), $qty, $uom, $fromLocation, $reason, $userId);
                $this->createLine($movement, $part->getKey(), $index + 1, $qty, $uom, $reason, $fromLocation, null, $resultingOnHand);
                break;
            case 'transfer':
                $this->assertLocationRequired($fromLocation, $lineKey . '.from_location_id', 'Select a source location.');
                $this->assertLocationRequired($toLocation, $lineKey . '.to_location_id', 'Select a destination location.');
                $this->assertDistinctLocations($fromLocation, $toLocation, $lineKey . '.to_location_id');
                $this->adjustInventory($companyId, $part->getKey(), $fromLocation, $uom, -1 * $qty, $lineKey . '.qty');
                $resultingOnHand = $this->adjustInventory($companyId, $part->getKey(), $toLocation, $uom, $qty, $lineKey . '.qty');
                $this->recordTxn($movement, InventoryTxnType::TransferOut, $part->getKey(), $qty, $uom, $fromLocation, $reason, $userId);
                $this->recordTxn($movement, InventoryTxnType::TransferIn, $part->getKey(), $qty, $uom, $toLocation, $reason, $userId);
                $this->createLine($movement, $part->getKey(), $index + 1, $qty, $uom, $reason, $fromLocation, $toLocation, $resultingOnHand);
                break;
            case 'adjust':
                if ($fromLocation === null && $toLocation === null) {
                    throw ValidationException::withMessages([
                        $lineKey . '.from_location_id' => 'Select a location to adjust.',
                    ]);
                }

                if ($fromLocation !== null) {
                    $resultingOnHand = $this->adjustInventory($companyId, $part->getKey(), $fromLocation, $uom, -1 * $qty, $lineKey . '.qty');
                    $this->recordTxn($movement, InventoryTxnType::AdjustOut, $part->getKey(), $qty, $uom, $fromLocation, $reason, $userId);
                }

                if ($toLocation !== null) {
                    $resultingOnHand = $this->adjustInventory($companyId, $part->getKey(), $toLocation, $uom, $qty, $lineKey . '.qty');
                    $this->recordTxn($movement, InventoryTxnType::AdjustIn, $part->getKey(), $qty, $uom, $toLocation, $reason, $userId);
                }

                $this->createLine($movement, $part->getKey(), $index + 1, $qty, $uom, $reason, $fromLocation, $toLocation, $resultingOnHand);
                break;
            default:
                throw ValidationException::withMessages([
                    'type' => 'Unsupported movement type.',
                ]);
        }
    }

    /**
     * @param array{warehouse_id:int,bin_id:?int}|null $location
     */
    private function createLine(
        InventoryMovement $movement,
        int $partId,
        int $lineNumber,
        float $qty,
        string $uom,
        ?string $reason,
        ?array $fromLocation,
        ?array $toLocation,
        ?float $resultingOnHand,
    ): void {
        InventoryMovementLine::query()->create([
            'company_id' => $movement->company_id,
            'movement_id' => $movement->getKey(),
            'line_number' => $lineNumber,
            'part_id' => $partId,
            'qty' => $qty,
            'uom' => $uom,
            'from_warehouse_id' => $fromLocation['warehouse_id'] ?? null,
            'from_bin_id' => $fromLocation['bin_id'] ?? null,
            'to_warehouse_id' => $toLocation['warehouse_id'] ?? null,
            'to_bin_id' => $toLocation['bin_id'] ?? null,
            'reason' => $reason,
            'resulting_on_hand' => $resultingOnHand,
        ]);
    }

    /**
     * @param array{warehouse_id:int,bin_id:?int}|null $location
     */
    private function recordTxn(
        InventoryMovement $movement,
        InventoryTxnType $type,
        int $partId,
        float $qty,
        string $uom,
        ?array $location,
        ?string $note,
        int $userId,
    ): void {
        if ($location === null) {
            return;
        }

        InventoryTxn::query()->create([
            'movement_id' => $movement->getKey(),
            'company_id' => $movement->company_id,
            'part_id' => $partId,
            'warehouse_id' => $location['warehouse_id'],
            'bin_id' => $location['bin_id'],
            'type' => $type,
            'qty' => $qty,
            'uom' => $uom,
            'ref_type' => $movement->reference_type,
            'ref_id' => $movement->reference_id,
            'note' => $note ?? $movement->notes,
            'performed_by' => $userId,
        ]);
    }

    /**
     * @param array{warehouse_id:int,bin_id:?int}|null $location
     */
    private function adjustInventory(
        int $companyId,
        int $partId,
        array $location,
        string $uom,
        float $delta,
        string $errorKey,
    ): float {
        $inventory = Inventory::query()->firstOrNew([
            'company_id' => $companyId,
            'part_id' => $partId,
            'warehouse_id' => $location['warehouse_id'],
            'bin_id' => $location['bin_id'],
        ]);

        if ($inventory->exists === false) {
            $inventory->on_hand = 0;
            $inventory->allocated = 0;
            $inventory->on_order = 0;
            $inventory->uom = $uom;
        }

        $next = (float) $inventory->on_hand + $delta;

        if ($next < -0.0005) {
            throw ValidationException::withMessages([
                $errorKey => 'Insufficient stock at the selected location.',
            ]);
        }

        $inventory->on_hand = $next;
        $inventory->uom = $uom;
        $inventory->save();

        return $next;
    }

    /**
     * @return array{warehouse_id:int,bin_id:?int}|null
     */
    private function resolveLocation(int $companyId, ?int $locationId, string $errorKey): ?array
    {
        if ($locationId === null) {
            return null;
        }

        $bin = Bin::query()
            ->with('warehouse:id,company_id,name,code')
            ->where('company_id', $companyId)
            ->whereKey($locationId)
            ->first();

        if ($bin !== null) {
            return [
                'warehouse_id' => $bin->warehouse_id,
                'bin_id' => $bin->getKey(),
            ];
        }

        $warehouse = Warehouse::query()
            ->where('company_id', $companyId)
            ->whereKey($locationId)
            ->first();

        if ($warehouse !== null) {
            return [
                'warehouse_id' => $warehouse->getKey(),
                'bin_id' => null,
            ];
        }

        throw ValidationException::withMessages([
            $errorKey => 'Selected location is invalid.',
        ]);
    }

    private function assertLocationRequired(?array $location, string $errorKey, string $message): void
    {
        if ($location === null) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function assertDistinctLocations(?array $from, ?array $to, string $errorKey): void
    {
        if ($from === null || $to === null) {
            return;
        }

        if ($from['warehouse_id'] === $to['warehouse_id'] && $from['bin_id'] === $to['bin_id']) {
            throw ValidationException::withMessages([
                $errorKey => 'Source and destination must be different.',
            ]);
        }
    }

    private function generateMovementNumber(int $companyId): string
    {
        $prefix = 'MV-' . now()->format('Ymd');

        $sequence = InventoryMovement::query()
            ->where('company_id', $companyId)
            ->where('movement_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
