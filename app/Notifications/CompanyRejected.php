<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CompanyRejected extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public Company $company,
        public string $reason,
        public string $audience = 'owner'
    ) {}

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
        $subject = $this->audience === 'owner'
            ? 'Your company application was rejected'
            : 'Company rejection notice';

        return (new MailMessage())
            ->subject($subject)
            ->markdown('emails.company.rejected', [
                'company' => $this->company,
                'recipient' => $notifiable,
                'audience' => $this->audience,
                'reason' => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->audience === 'owner'
            ? 'Your company application was rejected. Please review the reason and resubmit.'
            : sprintf('Company %s was rejected.', $this->company->name);

        return [
            'company_id' => $this->company->id,
            'status' => $this->company->status->value ?? 'rejected',
            'reason' => $this->reason,
            'message' => $message,
        ];
    }
}
