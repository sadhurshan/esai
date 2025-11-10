<?php

namespace App\Services\DigitalTwin;

use App\Models\Asset;
use App\Models\AssetProcedureLink;
use App\Models\MaintenanceProcedure;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\NotificationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

class MaintenancePlanner
{
    /**
     * @var list<string>
     */
    private const ALLOWED_UNITS = ['day', 'week', 'month', 'year', 'run_hours'];

    /**
     * @var list<string>
     */
    private const NOTIFICATION_ROLES = ['buyer_admin', 'ops_admin'];

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $schedule
     */
    public function linkProcedure(Asset $asset, MaintenanceProcedure $procedure, array $schedule): AssetProcedureLink
    {
        $frequencyValue = (int) ($schedule['frequency_value'] ?? 0);
        $frequencyUnit = strtolower((string) ($schedule['frequency_unit'] ?? ''));
        $lastDoneAt = $this->normalizeDateTime($schedule['last_done_at'] ?? null);
        $meta = $this->normalizeMeta($schedule['meta'] ?? []);

        if ($frequencyValue <= 0) {
            throw ValidationException::withMessages([
                'frequency_value' => ['Frequency value must be greater than zero.'],
            ]);
        }

        if (! in_array($frequencyUnit, self::ALLOWED_UNITS, true)) {
            throw ValidationException::withMessages([
                'frequency_unit' => ['Invalid frequency unit provided.'],
            ]);
        }

        $nextDueAt = $this->calculateNextDue($frequencyValue, $frequencyUnit, $lastDoneAt);

        return $this->database->transaction(function () use ($asset, $procedure, $frequencyValue, $frequencyUnit, $lastDoneAt, $nextDueAt, $meta): AssetProcedureLink {
            /** @var AssetProcedureLink|null $existing */
            $existing = AssetProcedureLink::query()
                ->where('asset_id', $asset->id)
                ->where('maintenance_procedure_id', $procedure->id)
                ->first();

            $payload = [
                'frequency_value' => $frequencyValue,
                'frequency_unit' => $frequencyUnit,
                'last_done_at' => $lastDoneAt,
                'next_due_at' => $nextDueAt,
                'meta' => $meta,
            ];

            if ($existing instanceof AssetProcedureLink) {
                $before = Arr::only($existing->getAttributes(), array_keys($payload));
                $existing->fill($payload);
                $existing->save();
                $this->auditLogger->updated($existing, $before, Arr::only($existing->getAttributes(), array_keys($payload)));

                return $existing;
            }

            $link = AssetProcedureLink::create(array_merge($payload, [
                'asset_id' => $asset->id,
                'maintenance_procedure_id' => $procedure->id,
            ]));

            $this->auditLogger->created($link, $payload);

            return $link;
        });
    }

    public function recordCompletion(Asset $asset, MaintenanceProcedure $procedure, Carbon $completedAt): AssetProcedureLink
    {
        /** @var AssetProcedureLink|null $link */
        $link = AssetProcedureLink::query()
            ->where('asset_id', $asset->id)
            ->where('maintenance_procedure_id', $procedure->id)
            ->first();

        if (! $link instanceof AssetProcedureLink) {
            throw ValidationException::withMessages([
                'procedure' => ['Maintenance procedure is not linked to this asset.'],
            ]);
        }

        $nextDueAt = $this->calculateNextDue($link->frequency_value, $link->frequency_unit, $completedAt);

        $before = Arr::only($link->getAttributes(), ['last_done_at', 'next_due_at']);
        $link->last_done_at = $completedAt;
        $link->next_due_at = $nextDueAt;
        $link->save();

        $this->auditLogger->updated($link, $before, [
            'last_done_at' => $link->last_done_at,
            'next_due_at' => $link->next_due_at,
        ]);

        $this->notifyCompletion($asset, $procedure, $link);

        return $link;
    }

    public function detachProcedure(Asset $asset, MaintenanceProcedure $procedure): void
    {
        $this->database->transaction(function () use ($asset, $procedure): void {
            /** @var AssetProcedureLink|null $link */
            $link = AssetProcedureLink::query()
                ->where('asset_id', $asset->id)
                ->where('maintenance_procedure_id', $procedure->id)
                ->first();

            if (! $link instanceof AssetProcedureLink) {
                return;
            }

            $before = $link->getAttributes();
            $link->delete();
            $this->auditLogger->deleted($link, $before);
        });
    }

    private function calculateNextDue(int $frequencyValue, string $frequencyUnit, ?Carbon $baseline = null): ?Carbon
    {
        $baseline = $baseline ? $baseline->copy() : Date::now();

        return match ($frequencyUnit) {
            'day' => $baseline->addDays($frequencyValue),
            'week' => $baseline->addWeeks($frequencyValue),
            'month' => $baseline->addMonths($frequencyValue),
            'year' => $baseline->addYears($frequencyValue),
            'run_hours' => null, // TODO: clarify with spec how to project hour-based frequencies.
            default => null,
        };
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse((string) $value);
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function normalizeMeta(mixed $meta): array
    {
        if ($meta === null) {
            return [];
        }

        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function notifyCompletion(Asset $asset, MaintenanceProcedure $procedure, AssetProcedureLink $link): void
    {
        $recipients = $this->resolveRecipients($asset);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $recipients,
            'maintenance_completed',
            sprintf('Maintenance completed: %s', $procedure->title),
            sprintf('Maintenance procedure %s completed for asset %s.', $procedure->title, $asset->name),
            Asset::class,
            $asset->id,
            [
                'procedure_id' => $procedure->id,
                'completed_at' => optional($link->last_done_at)?->toIso8601String(),
                'next_due_at' => optional($link->next_due_at)?->toIso8601String(),
            ]
        );
    }

    private function resolveRecipients(Asset $asset): Collection
    {
        $query = User::query()
            ->where('company_id', $asset->company_id)
            ->whereIn('role', self::NOTIFICATION_ROLES);

        // TODO: clarify with spec how asset watchers are modelled and include them when available.

        return $query->get();
    }
}
