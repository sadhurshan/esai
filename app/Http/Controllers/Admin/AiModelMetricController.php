<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ListAiModelMetricsRequest;
use App\Http\Resources\Admin\AiModelMetricResource;
use App\Models\AiModelMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AiModelMetricController extends ApiController
{
    public function index(ListAiModelMetricsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AiModelMetric::class);

        $perPage = $this->perPage($request, 50, 200);
        $from = $request->input('from');
        $to = $request->input('to');

        $metrics = AiModelMetric::query()
            ->when($request->filled('feature'), function (Builder $query) use ($request): void {
                $query->where('feature', $request->string('feature')->toString());
            })
            ->when($request->filled('metric_name'), function (Builder $query) use ($request): void {
                $query->where('metric_name', $request->string('metric_name')->toString());
            })
            ->when($from, function (Builder $query, string $value): void {
                $start = Carbon::parse($value)->startOfDay();
                $query->where('window_end', '>=', $start);
            })
            ->when($to, function (Builder $query, string $value): void {
                $end = Carbon::parse($value)->endOfDay();
                $query->where('window_end', '<=', $end);
            })
            ->orderByDesc('window_end')
            ->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($metrics, $request, AiModelMetricResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'AI model metrics retrieved.', $paginated['meta']);
    }
}
