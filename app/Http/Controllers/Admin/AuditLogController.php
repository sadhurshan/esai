<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ListAuditLogsRequest;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
class AuditLogController extends ApiController
{
    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $perPage = $this->perPage($request, 25, 100);

        $logs = AuditLog::query()
            ->with(['user:id,name,email'])
            ->when($request->filled('actor'), function (Builder $query) use ($request): void {
                $actor = $request->input('actor');
                $query->whereHas('user', function (Builder $builder) use ($actor): void {
                    $builder->where('name', 'like', "%{$actor}%")
                        ->orWhere('email', 'like', "%{$actor}%");
                });
            })
            ->when($request->filled('event'), fn (Builder $query) => $query->where('action', 'like', "%{$request->input('event')}%"))
            ->when($request->filled('resource'), function (Builder $query) use ($request): void {
                $resource = $request->input('resource');
                $query->where(function (Builder $inner) use ($resource): void {
                    $inner->where('entity_type', 'like', "%{$resource}%")
                        ->orWhere('entity_id', 'like', "%{$resource}%");
                });
            })
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($logs, $request, AuditLogResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Audit log retrieved.', $paginated['meta']);
    }
}
