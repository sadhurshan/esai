<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array $resource */
class HealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'app_version' => $this->resource['app_version'] ?? null,
            'php_version' => $this->resource['php_version'] ?? null,
            'queue_connection' => $this->resource['queue_connection'] ?? null,
            'database_connected' => $this->resource['database_connected'] ?? false,
            'pending_webhook_deliveries' => $this->resource['pending_webhook_deliveries'] ?? 0,
        ];
    }
}
