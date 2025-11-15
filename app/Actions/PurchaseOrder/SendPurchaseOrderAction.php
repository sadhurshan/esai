<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDelivery;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SendPurchaseOrderAction
{
    public function __construct(
        private readonly RecordPurchaseOrderEventAction $recordEvent,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param array{channel:string,to?:array<int,string>,cc?:array<int,string>,message?:string} $payload
     */
    public function execute(User $user, PurchaseOrder $purchaseOrder, array $payload): PurchaseOrderDelivery
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

        $data = $this->validatePayload($payload);

        $before = $purchaseOrder->getOriginal();

        $delivery = PurchaseOrderDelivery::create([
            'purchase_order_id' => $purchaseOrder->getKey(),
            'created_by_id' => $user->getKey(),
            'channel' => $data['channel'],
            'recipients_to' => Arr::get($data, 'to'),
            'recipients_cc' => Arr::get($data, 'cc'),
            'message' => $data['message'] ?? null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $purchaseOrder->sent_at = now();
        $purchaseOrder->ack_status = 'sent';
        $purchaseOrder->status = 'sent';
        $purchaseOrder->save();

        $this->auditLogger->updated($purchaseOrder, $before, [
            'status' => 'sent',
            'sent_at' => $purchaseOrder->sent_at,
        ]);

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

        return $delivery->fresh(['creator']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'channel' => ['required', 'string', Rule::in(['email', 'webhook'])],
            'to' => ['nullable', 'array'],
            'to.*' => ['required_with:to', 'email:rfc,dns'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['required_with:cc', 'email:rfc,dns'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $validator->after(function ($validator) use ($payload): void {
            if (($payload['channel'] ?? null) === 'email') {
                $recipients = Arr::get($payload, 'to', []);

                if (empty($recipients)) {
                    $validator->errors()->add('to', 'At least one email recipient is required for email delivery.');
                }
            }
        });

        return $validator->validate();
    }
}
