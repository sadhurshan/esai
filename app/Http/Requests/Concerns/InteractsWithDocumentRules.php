<?php

namespace App\Http\Requests\Concerns;

/**
 * Provides helpers for enforcing document size and extension rules from config.
 */
trait InteractsWithDocumentRules
{
    /**
     * @return list<string>
     */
    protected function documentAllowedExtensions(): array
    {
        $extensions = config('documents.allowed_extensions', []);

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            is_array($extensions) ? $extensions : []
        ), static fn (string $value): bool => $value !== ''));

        return $normalized === [] ? ['pdf'] : $normalized;
    }

    protected function documentMaxKilobytes(?int $overrideMb = null): int
    {
        $maxSizeMb = $overrideMb ?? (int) config('documents.max_size_mb', 50);

        return max(1, $maxSizeMb) * 1024;
    }
}
