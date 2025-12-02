<?php

namespace App\Support\Notifications;

use App\Events\NotificationDispatched;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param iterable<User> $recipients
     * @param array<string, mixed> $meta
     */
    public function send(
        iterable $recipients,
        string $eventType,
        string $title,
        string $body,
        string $entityType,
        ?int $entityId = null,
        array $meta = []
    ): void {
        $users = collect($recipients)
            ->filter(static fn ($user) => $user instanceof User)
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        $preferences = NotificationPreference::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('event_type', $eventType)
            ->get()
            ->keyBy('user_id');

        DB::transaction(function () use ($users, $preferences, $eventType, $title, $body, $entityType, $entityId, $meta): void {
            foreach ($users as $user) {
                /** @var NotificationPreference|null $preference */
                $preference = $preferences->get($user->id);
                $digest = $preference?->digest ?? 'none';
                $channel = $preference?->channel ?? 'both';

                $notification = Notification::create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                    'title' => $title,
                    'body' => $body,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'channel' => $digest === 'none' ? $channel : 'email',
                    'meta' => $meta,
                ]);

                $this->auditLogger->created($notification, [
                    'event_type' => $eventType,
                    'channel' => $notification->channel,
                ]);

                if ($digest !== 'none') {
                    continue;
                }

                broadcast(new NotificationDispatched($notification));

                if ($user->email) {
                    Mail::to($user->email)->queue(new NotificationMail($notification));
                }
            }
        });
    }

    public function markAsRead(Notification $notification, User $user): bool
    {
        if ((int) $notification->user_id !== (int) $user->id) {
            return false;
        }

        if ($notification->read_at !== null) {
            return true;
        }

        $notification->read_at = now();
        $notification->save();

        $this->auditLogger->updated($notification, ['read_at' => null], ['read_at' => $notification->read_at]);

        return true;
    }

    /**
     * @param  list<int>  $ids
     */
    public function markSelectedAsRead(User $user, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get();

        $updated = 0;

        foreach ($notifications as $notification) {
            if ($this->markAsRead($notification, $user)) {
                $updated++;
            }
        }

        return $updated;
    }

    public function markAllAsRead(User $user): int
    {
        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->get();

        $updated = 0;

        foreach ($notifications as $notification) {
            if ($this->markAsRead($notification, $user)) {
                $updated++;
            }
        }

        return $updated;
    }
}
