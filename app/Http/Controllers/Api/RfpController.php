<?php

namespace App\Http\Controllers\Api;

use App\Actions\Rfp\CreateRfpAction;
use App\Actions\Rfp\TransitionRfpStatusAction;
use App\Actions\Rfp\UpdateRfpAction;
use App\Enums\RfpStatus;
use App\Http\Requests\Rfp\StoreRfpRequest;
use App\Http\Requests\Rfp\UpdateRfpRequest;
use App\Http\Resources\RfpResource;
use App\Models\Rfp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RfpController extends ApiController
{
    public function __construct(
        private readonly CreateRfpAction $createRfpAction,
        private readonly UpdateRfpAction $updateRfpAction,
        private readonly TransitionRfpStatusAction $transitionRfpStatusAction,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $paginator = Rfp::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RfpResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }

    public function store(StoreRfpRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];

        if ($this->authorizeDenied($user, 'create', Rfp::class)) {
            return $this->fail('RFP creation requires sourcing write access.', 403, [
                'code' => 'rfps_write_required',
            ]);
        }

        $rfp = $this->createRfpAction->execute($user, $request->validated());

        return $this->ok((new RfpResource($rfp))->toArray($request), 'RFP created')
            ->setStatusCode(201);
    }

    public function show(Rfp $rfp, Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];

        if ($this->authorizeDenied($user, 'view', $rfp)) {
            return $this->fail('Project RFP access required.', 403, [
                'code' => 'rfps_read_required',
            ]);
        }

        return $this->ok((new RfpResource($rfp))->toArray($request));
    }

    public function update(Rfp $rfp, UpdateRfpRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];

        if ($this->authorizeDenied($user, 'update', $rfp)) {
            return $this->fail('RFP update requires sourcing write access.', 403, [
                'code' => 'rfps_write_required',
            ]);
        }

        $rfp = $this->updateRfpAction->execute($rfp, $request->validated(), $user);

        return $this->ok((new RfpResource($rfp))->toArray($request), 'RFP updated');
    }

    public function publish(Rfp $rfp, Request $request): JsonResponse
    {
        return $this->transition($rfp, $request, RfpStatus::Published, 'RFP published');
    }

    public function moveToReview(Rfp $rfp, Request $request): JsonResponse
    {
        return $this->transition($rfp, $request, RfpStatus::InReview, 'RFP moved to review');
    }

    public function award(Rfp $rfp, Request $request): JsonResponse
    {
        return $this->transition($rfp, $request, RfpStatus::Awarded, 'RFP awarded');
    }

    public function closeWithoutAward(Rfp $rfp, Request $request): JsonResponse
    {
        return $this->transition($rfp, $request, RfpStatus::NoAward, 'RFP closed without award');
    }

    private function transition(Rfp $rfp, Request $request, RfpStatus $target, string $successMessage): JsonResponse
    {
        $context = $this->requireCompanyContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];

        if ($this->authorizeDenied($user, 'update', $rfp)) {
            return $this->fail('RFP update requires sourcing write access.', 403, [
                'code' => 'rfps_write_required',
            ]);
        }

        try {
            $rfp = $this->transitionRfpStatusAction->execute($rfp, $target, $user);
        } catch (InvalidArgumentException $exception) {
            return $this->fail($exception->getMessage(), 422, [
                'code' => 'rfp_transition_invalid',
            ]);
        }

        return $this->ok((new RfpResource($rfp))->toArray($request), $successMessage);
    }
}
