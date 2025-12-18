<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateApiSession
{
    /**
     * Handle an incoming request by resolving an authenticated user from session storage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $sessionId = $this->resolveSessionId($request);

        if ($sessionId === null) {
            return $next($request);
        }

        $session = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->first();

        if (! $session || ! $session->user_id) {
            return $next($request);
        }

        $user = User::find((int) $session->user_id);

        if (! $user instanceof Authenticatable) {
            return $next($request);
        }

        Auth::onceUsingId($user->getAuthIdentifier());
        $request->setUserResolver(static fn () => $user);
        $this->attachSessionPersona($request, $session);

        $this->touchSession($sessionId);

        return $next($request);
    }

    private function attachSessionPersona(Request $request, object $session): void
    {
        if ($request->attributes->has('session.active_persona')) {
            return;
        }

        $persona = $this->extractActivePersona($session->payload ?? null);

        if ($persona !== null) {
            $request->attributes->set('session.active_persona', $persona);
        }
    }

    private function extractActivePersona(mixed $payload): ?array
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = base64_decode($payload, true);
        $serialized = $decoded !== false ? $decoded : $payload;

        try {
            $data = unserialize($serialized, ['allowed_classes' => true]);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $persona = $data['active_persona'] ?? null;

        return is_array($persona) ? $persona : null;
    }

    private function resolveSessionId(Request $request): ?string
    {
        $cookieName = config('session.cookie');

        if ($cookieName && $request->cookies->has($cookieName)) {
            $value = (string) $request->cookies->get($cookieName);

            if ($value !== '') {
                $sessionId = $this->decryptSessionCookie($value);

                if ($sessionId !== null) {
                    return $sessionId;
                }
            }
        }

        $bearer = $request->bearerToken();

        return $bearer !== '' ? $bearer : null;
    }

    private function decryptSessionCookie(string $value): ?string
    {
        try {
            $payload = decrypt($value, false);
        } catch (DecryptException) {
            $payload = $value;
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        if (str_contains($payload, '|')) {
            [, $sessionId] = explode('|', $payload, 2);

            return $sessionId !== '' ? $sessionId : null;
        }

        return $payload;
    }

    private function touchSession(string $sessionId): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->update([
                'last_activity' => Carbon::now()->getTimestamp(),
            ]);

        // Optionally prune expired sessions here if desired. Not required for now.
    }
}
