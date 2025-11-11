<?php

namespace App\Services\Admin;

use App\Models\WebhookDelivery;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Queue;

class HealthService
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'app_version' => config('app.version'),
            'php_version' => PHP_VERSION,
            'queue_connection' => config('queue.default'),
            'database_connected' => $this->canConnectToDatabase(),
            'pending_webhook_deliveries' => WebhookDelivery::query()->where('status', 'pending')->count(),
        ];
    }

    private function canConnectToDatabase(): bool
    {
        try {
            $this->connection->getPdo();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
