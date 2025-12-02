<?php

namespace App\Enums;

enum DigitalTwinAuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Published = 'published';
    case Archived = 'archived';
    case AssetAdded = 'asset_added';
    case AssetRemoved = 'asset_removed';
    case SpecChanged = 'spec_changed';

    public function requiresMeta(): bool
    {
        return match ($this) {
            self::AssetAdded,
            self::AssetRemoved,
            self::SpecChanged => true,
            default => false,
        };
    }
}
