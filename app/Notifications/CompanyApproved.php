<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CompanyApproved extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(public Company $company, public string $audience = 'owner') {}

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
            ? 'Your company was approved'
            : 'Company approval notice';

        return (new MailMessage())
            ->subject($subject)
            ->markdown('emails.company.approved', [
                'company' => $this->company,
                'recipient' => $notifiable,
                'audience' => $this->audience,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->audience === 'owner'
            ? 'Your company has been approved and can access supplier workflows.'
            : sprintf('Company %s has been approved.', $this->company->name);

        return [
            'company_id' => $this->company->id,
            'status' => $this->company->status->value ?? 'approved',
            'message' => $message,
        ];
    }
}
