<?php

namespace App\Services\DigitalTwin;

use App\Enums\DigitalTwinAssetType;
use App\Enums\DigitalTwinAuditEvent as DigitalTwinAuditEventEnum;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAsset;
use App\Models\DigitalTwinAuditEvent;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DigitalTwinAssetService
{
    public function __construct(private readonly FilesystemManager $filesystemManager)
    {
    }

    /**
     * @param  array{type: string|null, is_primary?: bool|null, meta?: array<string, mixed>|null}  $options
     */
    public function store(User $actor, DigitalTwin $twin, UploadedFile $file, array $options = []): DigitalTwinAsset
    {
        $diskName = config('digital-twins.assets.disk');
        $disk = $this->filesystemManager->disk($diskName);

        $checksum = hash_file('sha256', $file->getRealPath());

        $path = $disk->putFileAs(
            $this->buildBasePath($twin),
            $file,
            $this->buildFilename($file)
        );

        if (! $path) {
            throw new RuntimeException('Failed to store digital twin asset.');
        }

        $type = DigitalTwinAssetType::tryFrom(strtoupper((string) ($options['type'] ?? $file->extension()))) ?? DigitalTwinAssetType::Other;

        $asset = $twin->assets()->create([
            'disk' => $diskName,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
            'is_primary' => (bool) ($options['is_primary'] ?? false),
            'type' => $type,
            'meta' => $options['meta'] ?? null,
        ]);

        if ($asset->is_primary) {
            $this->markPrimary($asset);
        }

        $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::AssetAdded, [
            'asset_id' => $asset->id,
            'filename' => $asset->filename,
            'type' => $asset->type?->value,
        ]);

        return $asset;
    }

    public function delete(User $actor, DigitalTwin $twin, DigitalTwinAsset $asset): void
    {
        $diskName = $asset->disk ?? config('digital-twins.assets.disk');
        $disk = $this->filesystemManager->disk($diskName);

        if ($asset->path && $disk->exists($asset->path)) {
            $disk->delete($asset->path);
        }

        $asset->delete();

        $this->recordAuditEvent($twin, $actor, DigitalTwinAuditEventEnum::AssetRemoved, [
            'asset_id' => $asset->id,
            'filename' => $asset->filename,
        ]);
    }

    public function markPrimary(DigitalTwinAsset $asset): void
    {
        DigitalTwinAsset::where('digital_twin_id', $asset->digital_twin_id)
            ->whereKeyNot($asset->id)
            ->update(['is_primary' => false]);
    }

    private function buildBasePath(DigitalTwin $twin): string
    {
        return 'digital-twins/'.$twin->id.'/assets';
    }

    private function buildFilename(UploadedFile $file): string
    {
        $slug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension() ?: $file->extension();

        return trim($slug !== '' ? $slug.'-'.Str::random(6) : Str::random(12)).'.'.$extension;
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
