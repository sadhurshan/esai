<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Http\Resources\Admin\CompanyResource;
use App\Models\PlatformAdmin;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Http\JsonResponse;

class AdminAnalyticsController extends Controller
{
    public function __construct(private readonly AdminAnalyticsService $analyticsService)
    {
    }

    public function overview(): JsonResponse
    {
        $this->authorize('overview', PlatformAdmin::class);

        $data = $this->analyticsService->overview();

        $recentCompanies = CompanyResource::collection($data['recent_companies'])->resolve(request());
        $recentAuditLogs = AuditLogResource::collection($data['recent_audit_logs'])->resolve(request());

        unset($data['recent_companies'], $data['recent_audit_logs']);

        $data['recent'] = [
            'companies' => $recentCompanies,
            'audit_logs' => $recentAuditLogs,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Admin analytics overview retrieved.',
            'data' => $data,
        ]);
    }
}
