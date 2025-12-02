<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\PreviewEmailTemplateRequest;
use App\Http\Requests\Admin\StoreEmailTemplateRequest;
use App\Http\Requests\Admin\UpdateEmailTemplateRequest;
use App\Http\Resources\Admin\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\Admin\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailTemplateController extends ApiController
{
    public function __construct(private readonly EmailTemplateService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $perPage = $this->perPage($request, 25, 100);

        $templates = EmailTemplate::query()
            ->orderBy('key')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($templates, $request, EmailTemplateResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Email templates retrieved.', $paginated['meta']);
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        $template = $this->service->create($request->validated());

        return $this->ok([
            'template' => (new EmailTemplateResource($template))->toArray($request),
        ], 'Email template created.')->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        $this->authorize('view', $emailTemplate);

        return $this->ok([
            'template' => (new EmailTemplateResource($emailTemplate))->toArray(request()),
        ], 'Email template retrieved.');
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $template = $this->service->update($emailTemplate, $request->validated());

        return $this->ok([
            'template' => (new EmailTemplateResource($template))->toArray($request),
        ], 'Email template updated.');
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        $this->authorize('delete', $emailTemplate);

        $this->service->delete($emailTemplate);

        return $this->ok(null, 'Email template deleted.');
    }

    public function preview(PreviewEmailTemplateRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $preview = $this->service->preview($emailTemplate, $request->payload());

        return $this->ok($preview, 'Email template preview generated.');
    }
}
