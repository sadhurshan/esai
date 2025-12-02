<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Notifications\MarkNotificationsReadRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Support\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $query = Notification::query()
            ->with(['user', 'company'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $status = strtolower((string) $request->query('status'));

        if ($status === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($status === 'unread') {
            $query->whereNull('read_at');
        }

        $paginator = $query->cursorPaginate($this->perPage($request));
        $paginated = $this->paginate($paginator, $request, NotificationResource::class);
        $unreadCount = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $meta = array_merge($paginated['meta'], [
            'unread_count' => $unreadCount,
        ]);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Notifications retrieved.', $meta);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'update', $notification)) {
            return $this->fail('Forbidden.', 403);
        }

        $this->notificationService->markAsRead($notification, $user);

        return $this->ok((new NotificationResource($notification->fresh()))->toArray($request), 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $count = $this->notificationService->markAllAsRead($user);

        return $this->ok(['updated' => $count], 'Notifications marked as read.');
    }

    public function markSelectedRead(MarkNotificationsReadRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $payload = $request->payload();
        $updated = $this->notificationService->markSelectedAsRead($user, $payload['ids']);

        return $this->ok([
            'updated' => $updated,
        ], 'Notifications marked as read.');
    }
}
