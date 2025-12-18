<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\UserAvatarService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends ApiController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UserAvatarService $avatarService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        return $this->ok(UserProfileResource::make($user), 'Profile retrieved.');
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $payload = $request->validated();
        $avatarFile = $request->file('avatar');

        unset($payload['avatar']);

        if ($avatarFile) {
            $payload['avatar_path'] = $this->avatarService->store($user, $avatarFile);
        } elseif ($request->exists('avatar_path') && ($payload['avatar_path'] ?? null) === null) {
            $this->avatarService->delete($user);
        }

        $before = $user->only(array_keys($payload));

        $user->fill($payload);

        if (array_key_exists('email', $payload) && $user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $after = $user->only(array_keys($payload));

        $this->auditLogger->updated($user, $before, $after);

        return $this->ok(UserProfileResource::make($user), 'Profile updated.');
    }
}
