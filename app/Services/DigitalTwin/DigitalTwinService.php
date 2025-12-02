<?php

namespace App\Services\DigitalTwin;

use App\Enums\DigitalTwinAuditEvent as DigitalTwinAuditEventEnum;
use App\Enums\DigitalTwinStatus;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAuditEvent;
use App\Models\DigitalTwinSpec;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigitalTwinService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array{category_id?: int|null, code?: string|null, title: string, summary?: string|null, tags?: array<int, string>, version?: string, revision_notes?: string|null, visibility?: string, thumbnail_path?: string|null, specs?: array<int, array{name: string, value: string, uom?: string|null, sort_order?: int|null}>}  $data
     */
    public function create(User $actor, array $data): DigitalTwin
    {
        return DB::transaction(function () use ($actor, $data): DigitalTwin {
            $payload = $this->preparePayload($data);
            $payload['status'] = DigitalTwinStatus::Draft;
            $payload['company_id'] = null;

            $twin = DigitalTwin::create($payload);

            $this->syncSpecs($twin, $data['specs'] ?? []);

            $this->auditLogger->created($twin, $twin->toArray());
            $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::Created, [
                'title' => $twin->title,
            ]);

            if (! empty($data['specs'])) {
                $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::SpecChanged);
            }

            return $twin->load(['category', 'specs']);
        });
    }

    /**
     * @param  array{category_id?: int|null, code?: string|null, title?: string, summary?: string|null, tags?: array<int, string>, version?: string, revision_notes?: string|null, visibility?: string, thumbnail_path?: string|null, specs?: array<int, array{name: string, value: string, uom?: string|null, sort_order?: int|null}>}  $data
     */
    public function update(User $actor, DigitalTwin $twin, array $data): DigitalTwin
    {
        return DB::transaction(function () use ($actor, $twin, $data): DigitalTwin {
            $before = $twin->replicate()->toArray();

            $payload = $this->preparePayload($data, $twin);
            $twin->fill($payload);
            $twin->save();

            $specsChanged = false;

            if (array_key_exists('specs', $data)) {
                $this->syncSpecs($twin, $data['specs'] ?? []);
                $specsChanged = true;
            }

            $this->auditLogger->updated($twin, $before, $twin->toArray());
            $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::Updated, [
                'changed' => array_keys($twin->getChanges()),
            ]);

            if ($specsChanged) {
                $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::SpecChanged);
            }

            return $twin->load(['category', 'specs']);
        });
    }

    public function publish(User $actor, DigitalTwin $twin): DigitalTwin
    {
        if ($twin->status === DigitalTwinStatus::Published) {
            return $twin;
        }

        $twin->forceFill([
            'status' => DigitalTwinStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ])->save();

        $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::Published);

        return $twin->refresh();
    }

    public function archive(User $actor, DigitalTwin $twin): DigitalTwin
    {
        if ($twin->status === DigitalTwinStatus::Archived) {
            return $twin;
        }

        $twin->forceFill([
            'status' => DigitalTwinStatus::Archived,
            'archived_at' => now(),
        ])->save();

        $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::Archived);

        return $twin->refresh();
    }

    public function delete(User $actor, DigitalTwin $twin): void
    {
        $before = $twin->toArray();
        $twin->delete();

        $this->auditLogger->deleted($twin, $before);
        $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::Archived, [
            'reason' => 'deleted',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?DigitalTwin $twin = null): array
    {
        $payload = Arr::only($data, [
            'category_id',
            'code',
            'title',
            'summary',
            'version',
            'revision_notes',
            'visibility',
            'thumbnail_path',
        ]);

        if (array_key_exists('tags', $data)) {
            $payload['tags'] = array_values(array_filter(array_map(static fn ($tag) => is_string($tag) ? trim($tag) : null, $data['tags']), static fn ($tag) => ! empty($tag)));
        }

        if (! empty($payload['code'])) {
            $payload['code'] = Str::upper($payload['code']);
        }

        return $payload;
    }

    /**
     * @param  array<int, array{name: string, value: string, uom?: string|null, sort_order?: int|null}>  $specs
     */
    private function syncSpecs(DigitalTwin $twin, array $specs): void
    {
        $specIdsToKeep = [];
        $order = 1;

        foreach ($specs as $specData) {
            $spec = null;
            $specId = $specData['id'] ?? null;

            if ($specId !== null) {
                $spec = DigitalTwinSpec::where('digital_twin_id', $twin->id)->whereKey($specId)->first();
            }

            if ($spec === null) {
                $spec = new DigitalTwinSpec([
                    'digital_twin_id' => $twin->id,
                    'name' => $specData['name'],
                ]);
            }

            $spec->fill([
                'name' => $specData['name'],
                'value' => $specData['value'],
                'uom' => $specData['uom'] ?? null,
                'sort_order' => $specData['sort_order'] ?? $order,
            ]);

            $spec->save();

            $specIdsToKeep[] = $spec->id;
            $order++;
        }

        DigitalTwinSpec::where('digital_twin_id', $twin->id)
            ->whereNotIn('id', $specIdsToKeep)
            ->delete();
    }

    private function recordAuditEvent(DigitalTwin $twin, User $actor, DigitalTwinAuditEventEnum $event, array $meta = []): void
    {
        DigitalTwinAuditEvent::create([
            'digital_twin_id' => $twin->id,
            'actor_id' => $actor->id,
            'event' => $event->value,
            'meta' => $meta ?: null,
        ]);
    }
}
