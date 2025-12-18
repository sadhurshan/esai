<?php

use App\Http\Middleware\AuthenticateApiSession;
use App\Http\Middleware\ResolveCompanyContext;
use Illuminate\Auth\Middleware\Authenticate as AuthenticateMiddleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'middleware' => [
        AuthenticateApiSession::class,
        StartSession::class,
        ResolveCompanyContext::class,
        AuthenticateMiddleware::class,
    ],
]);

Broadcast::channel('users.{userId}.notifications', function ($user, int $userId) {
    return (int) ($user->id ?? 0) === (int) $userId;
});
