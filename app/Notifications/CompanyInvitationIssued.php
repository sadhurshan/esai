<?php

namespace App\Notifications;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CompanyInvitationIssued extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  CompanyInvitation  $invitation
     */
    public function __construct(public CompanyInvitation $invitation)
    {
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function viaQueues(): array
    {
        return [
            'mail' => 'mail',
            'database' => 'default',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation->loadMissing('company', 'invitedBy');

        return (new MailMessage())
            ->subject($this->mailSubject())
            ->view('emails.company.invitation', [
                'invitation' => $invitation,
                'recipient' => $notifiable,
                'acceptUrl' => $this->acceptUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'company_id' => $this->invitation->company_id,
            'role' => $this->invitation->role,
            'message' => $this->invitation->message,
            'expires_at' => optional($this->invitation->expires_at)->toIso8601String(),
        ];
    }

    private function mailSubject(): string
    {
        $company = $this->invitation->company;
        $companyName = $company?->name ?? 'a workspace';

        return sprintf('You are invited to join %s on %s', $companyName, config('app.name'));
    }

    private function acceptUrl(): string
    {
        $base = config('app.frontend_url', config('app.url'));

        return rtrim($base ?? '', '/').'/invitations/accept?token='.urlencode($this->invitation->token).'&email='.urlencode($this->invitation->email);
    }
}
