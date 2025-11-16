<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->deny($request, 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if ($roles === [] || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return $this->deny($request, 'Forbidden.', Response::HTTP_FORBIDDEN);
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $message,
                'data' => null,
            ], $status);
        }

        if ($status === Response::HTTP_UNAUTHORIZED) {
            return redirect()->guest(route('login'));
        }

        return redirect()->route('dashboard');
    }
}
