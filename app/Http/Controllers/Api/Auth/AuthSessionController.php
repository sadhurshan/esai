<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthResponseFactory;
use App\Support\RequestPersonaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthSessionController extends ApiController
{
    public function __construct(private readonly AuthResponseFactory $responseFactory)
    {
    }

    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $remember = (bool) ($credentials['remember'] ?? false);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            return $this->fail('Invalid credentials provided.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        if ($user === null) {
            Auth::logout();

            return $this->fail('Authentication failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $payload = $this->responseFactory->make($user, $request->session()->getId());

        RequestPersonaResolver::remember($request, $payload['active_persona'] ?? null);

        return $this->ok($payload, 'Authenticated successfully.');
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->fail('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->responseFactory->make($user, $request->session()->getId());

        RequestPersonaResolver::remember($request, $payload['active_persona'] ?? null);

        return $this->ok($payload, 'Authentication details retrieved.');
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        RequestPersonaResolver::remember($request, null);

        return $this->ok(null, 'Signed out successfully.');
    }
}
