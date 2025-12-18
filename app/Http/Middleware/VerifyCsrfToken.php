<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var list<string>
     */
    protected $except = [
        'api/auth/*',
        'api/purchase-orders/*/send',
    ];

    /**
     * Always accept CSRF tokens when running the test suite.
     */
    protected function tokensMatch($request): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        return parent::tokensMatch($request);
    }
}
