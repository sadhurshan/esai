<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewEmailTemplateRequest;
use App\Http\Requests\Admin\StoreEmailTemplateRequest;
use App\Http\Requests\Admin\UpdateEmailTemplateRequest;
use App\Http\Resources\Admin\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\Admin\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class EmailTemplateController extends Controller
{
    public function __construct(private readonly EmailTemplateService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $templates = EmailTemplate::query()
            ->orderBy('key')
            ->cursorPaginate($perPage);

        return $this->paginatedResponse($templates, 'Email templates retrieved.');
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        $template = $this->service->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Email template created.',
            'data' => [
                'template' => EmailTemplateResource::make($template),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        $this->authorize('view', $emailTemplate);

        return response()->json([
            'status' => 'success',
            'message' => 'Email template retrieved.',
            'data' => [
                'template' => EmailTemplateResource::make($emailTemplate),
            ],
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $template = $this->service->update($emailTemplate, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Email template updated.',
            'data' => [
                'template' => EmailTemplateResource::make($template),
            ],
        ]);
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        $this->authorize('delete', $emailTemplate);

        $this->service->delete($emailTemplate);

        return response()->json([
            'status' => 'success',
            'message' => 'Email template deleted.',
            'data' => null,
        ]);
    }

    public function preview(PreviewEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $preview = $this->service->preview($emailTemplate, $request->payload());

        return response()->json([
            'status' => 'success',
            'message' => 'Email template preview generated.',
            'data' => $preview,
        ]);
    }

    private function paginatedResponse(CursorPaginator $paginator, string $message): JsonResponse
    {
        $items = EmailTemplateResource::collection(collect($paginator->items()))->resolve(request());

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
