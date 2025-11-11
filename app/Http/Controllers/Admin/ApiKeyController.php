<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RotateApiKeyRequest;
use App\Http\Requests\Admin\StoreApiKeyRequest;
use App\Http\Requests\Admin\ToggleApiKeyRequest;
use App\Http\Resources\Admin\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\Admin\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiKey::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $keys = ApiKey::query()
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->input('company_id')))
            ->when($request->filled('active'), fn ($query) => $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOL)))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return $this->paginatedResponse($keys, 'API keys retrieved.');
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'API key created.',
            'data' => [
                'api_key' => ApiKeyResource::make($result['api_key']),
                'plain_text_token' => $result['plain_text_token'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function rotate(RotateApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $result = $this->service->rotate($key);

        return response()->json([
            'status' => 'success',
            'message' => 'API key rotated.',
            'data' => [
                'api_key' => ApiKeyResource::make($result['api_key']),
                'plain_text_token' => $result['plain_text_token'],
            ],
        ]);
    }

    public function toggle(ToggleApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $apiKey = $this->service->toggle($key, $request->boolean('active'));

        return response()->json([
            'status' => 'success',
            'message' => 'API key state updated.',
            'data' => [
                'api_key' => ApiKeyResource::make($apiKey),
            ],
        ]);
    }

    public function destroy(ApiKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $this->service->delete($key);

        return response()->json([
            'status' => 'success',
            'message' => 'API key deleted.',
            'data' => null,
        ]);
    }

    private function paginatedResponse(CursorPaginator $paginator, string $message): JsonResponse
    {
        $items = ApiKeyResource::collection(collect($paginator->items()))->resolve(request());

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'items' => $items,
                'meta' => [
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
