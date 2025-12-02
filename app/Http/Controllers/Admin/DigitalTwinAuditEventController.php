<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DigitalTwinAuditEventResource;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class DigitalTwinAuditEventController extends ApiController
{
    public function index(Request $request, DigitalTwin $digitalTwin): JsonResponse
    {
        $this->authorize('view', $digitalTwin);

        $perPage = $this->perPage($request, 25, 100);

        $query = DigitalTwinAuditEvent::query()
            ->with('actor')
            ->where('digital_twin_id', $digitalTwin->id)
            ->latest('created_at');

        $paginator = $query->cursorPaginate($perPage)->withQueryString();

        $paginated = $this->paginate($paginator, $request, DigitalTwinAuditEventResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Digital twin audit events retrieved.', $paginated['meta']);
    }
}
