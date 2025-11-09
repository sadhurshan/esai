<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function build(): self
    {
        return $this->subject($this->notification->title)
            ->markdown('emails.notifications.generic', [
                'notification' => $this->notification,
            ]);
    }
}
