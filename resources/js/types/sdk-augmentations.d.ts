import type { RfqItem as BaseRfqItem, RfqLinePayload as BaseRfqLinePayload } from '@/sdk';

declare module '@/sdk' {
    interface RfqItem extends BaseRfqItem {
        requiredDate?: string | null;
    }

    interface RfqLinePayload extends BaseRfqLinePayload {
        requiredDate?: string | null;
    }
}
