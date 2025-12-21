<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ListModelTrainingJobsRequest;
use App\Http\Requests\Admin\StartAiTrainingRequest;
use App\Http\Resources\Admin\ModelTrainingJobResource;
use App\Models\ModelTrainingJob;
use App\Services\Ai\AiTrainingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class AiTrainingController extends ApiController
{
    public function __construct(private readonly AiTrainingService $trainingService)
    {
    }

    public function index(ListModelTrainingJobsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ModelTrainingJob::class);

        $perPage = $this->perPage($request, 50, 200);

        $jobs = ModelTrainingJob::query()
            ->when($request->filled('feature'), function (Builder $query) use ($request): void {
                $query->where('feature', $request->string('feature')->toString());
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('company_id'), function (Builder $query) use ($request): void {
                $query->where('company_id', $request->integer('company_id'));
            })
            ->when($request->filled('microservice_job_id'), function (Builder $query) use ($request): void {
                $query->where('microservice_job_id', $request->string('microservice_job_id')->toString());
            })
            ->when($request->filled('started_from'), function (Builder $query) use ($request): void {
                $from = Carbon::parse($request->input('started_from'))->startOfDay();
                $query->where('started_at', '>=', $from);
            })
            ->when($request->filled('started_to'), function (Builder $query) use ($request): void {
                $to = Carbon::parse($request->input('started_to'))->endOfDay();
                $query->where('started_at', '<=', $to);
            })
            ->when($request->filled('created_from'), function (Builder $query) use ($request): void {
                $from = Carbon::parse($request->input('created_from'))->startOfDay();
                $query->where('created_at', '>=', $from);
            })
            ->when($request->filled('created_to'), function (Builder $query) use ($request): void {
                $to = Carbon::parse($request->input('created_to'))->endOfDay();
                $query->where('created_at', '<=', $to);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($jobs, $request, ModelTrainingJobResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Model training jobs retrieved.', $paginated['meta']);
    }

    public function start(StartAiTrainingRequest $request): JsonResponse
    {
        $this->authorize('create', ModelTrainingJob::class);

        $payload = $request->validated();
        $feature = $payload['feature'];
        $parameters = Arr::except($payload, ['feature']);
        $extra = Arr::pull($parameters, 'parameters', []);

        if (is_array($extra) && $extra !== []) {
            $parameters = array_merge($parameters, $extra);
        }

        $job = $this->trainingService->startTraining($feature, $parameters);

        return $this->ok([
            'job' => new ModelTrainingJobResource($job),
        ], 'AI training job started.');
    }

    public function show(ModelTrainingJob $modelTrainingJob): JsonResponse
    {
        $this->authorize('view', $modelTrainingJob);

        return $this->ok(new ModelTrainingJobResource($modelTrainingJob), 'Model training job retrieved.');
    }

    public function refresh(ModelTrainingJob $modelTrainingJob): JsonResponse
    {
        $this->authorize('update', $modelTrainingJob);

        $this->trainingService->refreshStatus($modelTrainingJob);
        $modelTrainingJob = $modelTrainingJob->refresh();

        return $this->ok([
            'job' => new ModelTrainingJobResource($modelTrainingJob),
        ], 'Model training job status refreshed.');
    }
}
