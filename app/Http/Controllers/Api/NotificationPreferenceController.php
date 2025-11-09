<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Notifications\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $preferences = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->mapWithKeys(static fn (NotificationPreference $preference) => [
                $preference->event_type => [
                    'channel' => $preference->channel,
                    'digest' => $preference->digest,
                ],
            ]);

        return $this->ok($preferences->toArray(), 'Notification preferences retrieved.');
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $payload = $request->payload();

        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_type', $payload['event_type'])
            ->first();

        if ($preference !== null && $this->authorizeDenied($user, 'update', $preference)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($preference === null) {
            $preference = new NotificationPreference([
                'user_id' => $user->id,
                'event_type' => $payload['event_type'],
            ]);
        }

        $before = $preference->exists ? $preference->getOriginal() : [];

        $preference->channel = $payload['channel'];
        $preference->digest = $payload['digest'];
        $preference->user_id = $user->id;

        $dirty = $preference->getDirty();
        $dirtyKeys = array_keys($dirty);

        $preference->save();

        if ($preference->wasRecentlyCreated) {
            $this->auditLogger->created($preference, $preference->toArray());
        } else {
            $this->auditLogger->updated($preference, $before, $preference->only($dirtyKeys));
        }

        return $this->ok([
            'event_type' => $preference->event_type,
            'channel' => $preference->channel,
            'digest' => $preference->digest,
        ], 'Notification preference saved.');
    }
}
