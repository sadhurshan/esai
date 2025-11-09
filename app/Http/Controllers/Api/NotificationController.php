<?php

namespace App\Http\Controllers\Api;

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
            ->orderByDesc('created_at');

        $status = strtolower((string) $request->query('status'));

        if ($status === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($status === 'unread') {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($this->perPage($request));
        $paginated = $this->paginate($paginator, $request, NotificationResource::class);

        return $this->ok($paginated['items'], 'Notifications retrieved.', $paginated['meta']);
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
}
