<?php

namespace App\Notifications;

use App\Models\SupplierApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class SupplierApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(public SupplierApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function viaQueues(): array
    {
        return [
            'mail' => 'mail',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('New supplier application submitted')
            ->view('emails.supplier.application_submitted', [
                'application' => $this->application,
                'recipient' => $notifiable,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'company_id' => $this->application->company_id,
            'status' => $this->application->status->value,
            'message' => 'A new supplier application has been submitted.',
        ];
    }
}
