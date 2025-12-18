<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuoteShortlistController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function store(Request $request, Quote $quote): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $quote->company_id !== $companyId) {
            return $this->fail('Quote not found.', 404);
        }

        Gate::forUser($user)->authorize('manageShortlist', $quote);

        if ($quote->shortlisted_at === null) {
            $before = [
                'shortlisted_at' => $quote->shortlisted_at,
                'shortlisted_by' => $quote->shortlisted_by,
            ];

            $quote->forceFill([
                'shortlisted_at' => now(),
                'shortlisted_by' => $user->id,
            ])->save();

            $this->auditLogger->updated($quote, $before, [
                'shortlisted_at' => $quote->shortlisted_at,
                'shortlisted_by' => $quote->shortlisted_by,
            ]);
        }

        $quote->loadMissing(['supplier.company']);

        return $this->ok([
            'quote' => (new QuoteResource($quote))->toArray($request),
        ], 'Quote shortlisted');
    }

    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $quote->company_id !== $companyId) {
            return $this->fail('Quote not found.', 404);
        }

        Gate::forUser($user)->authorize('manageShortlist', $quote);

        if ($quote->shortlisted_at !== null) {
            $before = [
                'shortlisted_at' => $quote->shortlisted_at,
                'shortlisted_by' => $quote->shortlisted_by,
            ];

            $quote->forceFill([
                'shortlisted_at' => null,
                'shortlisted_by' => null,
            ])->save();

            $this->auditLogger->updated($quote, $before, [
                'shortlisted_at' => $quote->shortlisted_at,
                'shortlisted_by' => $quote->shortlisted_by,
            ]);
        }

        $quote->loadMissing(['supplier.company']);

        return $this->ok([
            'quote' => (new QuoteResource($quote))->toArray($request),
        ], 'Quote removed from shortlist');
    }
}
