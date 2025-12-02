<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\RotateApiKeyRequest;
use App\Http\Requests\Admin\StoreApiKeyRequest;
use App\Http\Requests\Admin\ToggleApiKeyRequest;
use App\Http\Resources\Admin\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\Admin\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyController extends ApiController
{
    public function __construct(private readonly ApiKeyService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiKey::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $paginator = ApiKey::query()
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->input('company_id')))
            ->when($request->filled('active'), fn ($query) => $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOL)))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, ApiKeyResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'API keys retrieved.', $paginated['meta']);
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        $response = $this->ok([
            'api_key' => (new ApiKeyResource($result['api_key']))->toArray($request),
            'token' => $result['plain_text_token'],
            'plain_text_token' => $result['plain_text_token'],
        ], 'API key created.');

        return $response->setStatusCode(Response::HTTP_CREATED);
    }

    public function rotate(RotateApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $result = $this->service->rotate($key);

        return $this->ok([
            'api_key' => (new ApiKeyResource($result['api_key']))->toArray($request),
            'token' => $result['plain_text_token'],
            'plain_text_token' => $result['plain_text_token'],
        ], 'API key rotated.');
    }

    public function toggle(ToggleApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $apiKey = $this->service->toggle($key, $request->boolean('active'));

        return $this->ok([
            'api_key' => (new ApiKeyResource($apiKey))->toArray($request),
        ], 'API key state updated.');
    }

    public function destroy(ApiKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $this->service->delete($key);

        return $this->ok(null, 'API key deleted.');
    }
}
