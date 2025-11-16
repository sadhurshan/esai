import type {
    AdminUpdateWebhookSubscriptionRequest as BaseAdminUpdateWebhookSubscriptionRequest,
    CreateRfqRequestItemsInner as BaseCreateRfqRequestItemsInner,
    RfqItem as BaseRfqItem,
    RfqLinePayload as BaseRfqLinePayload,
} from '@/sdk';

declare module '@/sdk' {
    interface RfqItem extends BaseRfqItem {
        requiredDate?: string | null;
    }

    interface RfqLinePayload extends BaseRfqLinePayload {
        requiredDate?: string | null;
    }

    interface CreateRfqRequestItemsInner extends BaseCreateRfqRequestItemsInner {
        requiredDate?: string | null;
    }

    interface AdminUpdateWebhookSubscriptionRequest extends BaseAdminUpdateWebhookSubscriptionRequest {
        secret?: string;
    }
}
