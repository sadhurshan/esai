<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ListAiEventsRequest;
use App\Http\Resources\Admin\AiEventResource;
use App\Models\AiEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AiEventController extends ApiController
{
    public function index(ListAiEventsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AiEvent::class);

        $perPage = $this->perPage($request, 25, 100);

        $events = AiEvent::query()
            ->with(['user:id,name,email'])
            ->when($request->filled('feature'), function (Builder $query) use ($request): void {
                $query->where('feature', 'like', '%' . $request->input('feature') . '%');
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('entity'), function (Builder $query) use ($request): void {
                $entity = $request->input('entity');
                $query->where(function (Builder $builder) use ($entity): void {
                    $builder->where('entity_type', 'like', "%{$entity}%")
                        ->orWhere('entity_id', 'like', "%{$entity}%");
                });
            })
            ->when($request->filled('from'), function (Builder $query) use ($request): void {
                $from = Carbon::parse($request->input('from'));
                $query->where('created_at', '>=', $from);
            })
            ->when($request->filled('to'), function (Builder $query) use ($request): void {
                $to = Carbon::parse($request->input('to'));
                $query->where('created_at', '<=', $to);
            })
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($events, $request, AiEventResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'AI activity log retrieved.', $paginated['meta']);
    }
}
