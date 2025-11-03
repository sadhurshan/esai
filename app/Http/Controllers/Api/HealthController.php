<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->ok([
            'healthy' => true,
            'app' => config('app.name'),
            'env' => app()->environment(),
            'debug' => false,
        ]);
    }
}
