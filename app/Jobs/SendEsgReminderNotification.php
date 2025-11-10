<?php

namespace App\Jobs;

use App\Models\SupplierEsgRecord;
use App\Support\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEsgReminderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $recordId, public string $reason)
    {
        $this->onQueue(config('queue.names.notifications', 'notifications'));
    }

    public function handle(NotificationService $notifications): void
    {
        $record = SupplierEsgRecord::query()
            ->with(['supplier', 'company'])
            ->find($this->recordId);

        if ($record === null || $record->company === null) {
            return;
        }

        $recipients = $record->company
            ->users()
            ->whereIn('role', ['buyer_admin', 'supplier_admin', 'platform_super'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $title = match ($this->reason) {
            'expired_certificate' => 'Supplier ESG certificate expired',
            'missing_emission_data' => 'Supplier emission data missing',
            default => 'Supplier ESG reminder',
        };

        $body = match ($this->reason) {
            'expired_certificate' => sprintf(
                'The ESG certificate "%s" for supplier %s expired on %s.',
                $record->name,
                $record->supplier?->name ?? 'unknown supplier',
                $record->expires_at?->toDateString() ?? 'an unknown date'
            ),
            'missing_emission_data' => sprintf(
                'Emission data for "%s" requires updates before generating Scope-3 exports.',
                $record->name
            ),
            default => sprintf('ESG record "%s" requires attention.', $record->name),
        };

        $notifications->send(
            $recipients,
            'esg_reminder',
            $title,
            $body,
            SupplierEsgRecord::class,
            $record->id,
            [
                'supplier_id' => $record->supplier_id,
                'reason' => $this->reason,
            ]
        );
    }
}
