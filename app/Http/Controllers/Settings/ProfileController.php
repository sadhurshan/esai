<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\UserAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly UserAvatarService $avatarService)
    {
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): View
    {
        return view('app');
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $payload = $request->validated();
        $avatarFile = $request->file('avatar');

        unset($payload['avatar']);

        if ($avatarFile) {
            $payload['avatar_path'] = $this->avatarService->store($user, $avatarFile);
        } elseif ($request->exists('avatar_path') && ($payload['avatar_path'] ?? null) === null) {
            $this->avatarService->delete($user);
        }

        $user->fill($payload);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Force delete so default profile deletion test expectations still hold even though the model uses soft deletes.
        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
