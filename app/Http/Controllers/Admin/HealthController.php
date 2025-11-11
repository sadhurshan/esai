<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HealthResource;
use App\Services\Admin\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(private readonly HealthService $service)
    {
    }

    public function show(): JsonResponse
    {
        $payload = $this->service->summary();

        return response()->json([
            'status' => 'success',
            'message' => 'Platform health retrieved.',
            'data' => HealthResource::make($payload),
        ]);
    }
}
