<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Admin\HealthResource;
use App\Services\Admin\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends ApiController
{
    public function __construct(private readonly HealthService $service)
    {
    }

    public function show(): JsonResponse
    {
        $payload = $this->service->summary();

        return $this->ok((new HealthResource($payload))->toArray(request()), 'Platform health retrieved.');
    }
}
