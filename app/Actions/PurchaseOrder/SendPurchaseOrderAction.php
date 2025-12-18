<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDelivery;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SendPurchaseOrderAction
{
    public function __construct(
        private readonly RecordPurchaseOrderEventAction $recordEvent,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param array{message?:string} $payload
     * @return Collection<int, PurchaseOrderDelivery>
     */
    public function execute(User $user, PurchaseOrder $purchaseOrder, array $payload): Collection
    {
        if ($user->company_id === null || (int) $purchaseOrder->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not found for this company.'],
            ]);
        }

        if (! in_array($purchaseOrder->status, ['draft', 'sent'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft purchase orders can be issued to suppliers.'],
            ]);
        }

        $purchaseOrder->loadMissing(['supplier', 'quote.supplier']);

        $data = $this->validatePayload($payload);
        $message = Arr::get($data, 'message');
        $supplierEmail = $this->resolveSupplierEmail($purchaseOrder);
        $overrideEmail = Arr::get($data, 'override_email');

        if ($supplierEmail === null && $overrideEmail) {
            $supplierEmail = $overrideEmail;
        }

        if ($supplierEmail === null) {
            throw ValidationException::withMessages([
                'supplier' => ['A supplier email address is required before sending this purchase order.'],
            ]);
        }

        $before = $purchaseOrder->getOriginal();
        $timestamp = now();

        $deliveries = collect([
            $this->createDelivery($purchaseOrder, $user, 'email', [$supplierEmail], $message, $timestamp),
            $this->createDelivery($purchaseOrder, $user, 'webhook', null, $message, $timestamp),
        ]);

        $purchaseOrder->sent_at = $timestamp;
        $purchaseOrder->ack_status = 'sent';
        $purchaseOrder->status = 'sent';
        $purchaseOrder->save();

        $this->auditLogger->updated($purchaseOrder, $before, [
            'status' => 'sent',
            'sent_at' => $purchaseOrder->sent_at,
        ]);

        foreach ($deliveries as $delivery) {
            $this->recordEvent->execute(
                $purchaseOrder,
                'sent',
                sprintf('PO delivered via %s', $delivery->channel),
                null,
                [
                    'delivery_id' => $delivery->getKey(),
                    'channel' => $delivery->channel,
                    'recipients_to' => $delivery->recipients_to,
                    'recipients_cc' => $delivery->recipients_cc,
                ],
                $user,
                $delivery->sent_at,
            );

            Log::info('purchase_order.sent', [
                'purchase_order_id' => $purchaseOrder->id,
                'channel' => $delivery->channel,
                'actor_id' => $user->id,
            ]);
        }

        $deliveries->each->load('creator');

        return $deliveries;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'message' => ['nullable', 'string', 'max:2000'],
            'override_email' => ['nullable', 'email:rfc'],
        ]);

        return $validator->validate();
    }

    private function resolveSupplierEmail(PurchaseOrder $purchaseOrder): ?string
    {
        $email = $purchaseOrder->supplier?->email;

        if ($email) {
            return $email;
        }

        return $purchaseOrder->quote?->supplier?->email;
    }

    private function createDelivery(
        PurchaseOrder $purchaseOrder,
        User $user,
        string $channel,
        ?array $recipientsTo,
        ?string $message,
        CarbonInterface $sentAt,
    ): PurchaseOrderDelivery {
        return PurchaseOrderDelivery::create([
            'purchase_order_id' => $purchaseOrder->getKey(),
            'created_by_id' => $user->getKey(),
            'channel' => $channel,
            'recipients_to' => $recipientsTo,
            'recipients_cc' => null,
            'message' => $message,
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);
    }
}
