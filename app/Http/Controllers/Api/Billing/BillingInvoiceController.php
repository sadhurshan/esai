<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Api\ApiController;
use App\Services\Billing\StripeInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingInvoiceController extends ApiController
{
    public function __construct(private readonly StripeInvoiceService $invoiceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user?->company;

        if ($company === null) {
            return $this->fail('Active company context required.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'code' => 'company_context_missing',
            ]);
        }

        $result = $this->invoiceService->listRecentInvoices($company);

        if (! $result->successful) {
            return $this->fail($result->message ?? 'Unable to load invoices.', Response::HTTP_BAD_GATEWAY, [
                'code' => $result->code ?? 'invoice_history_unavailable',
            ]);
        }

        return $this->ok([
            'items' => $result->invoices,
        ], 'Invoice history loaded.', [
            'envelope' => [
                'count' => count($result->invoices),
            ],
        ]);
    }
}
