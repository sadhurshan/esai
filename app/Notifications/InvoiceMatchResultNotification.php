<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceMatchResultNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, int> $summary
     * @param array<int, array<string, mixed>> $mismatches
     */
    public function __construct(
        private readonly Invoice $invoice,
        private readonly array $summary,
        private readonly array $mismatches
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'purchase_order_id' => $this->invoice->purchase_order_id,
            'summary' => $this->summary,
            'mismatches' => $this->mismatches,
        ];
    }
}
