import { useMutation, useQueryClient } from '@tanstack/react-query';

import type { RfqMethod } from '@/constants/rfq';
import { useApiClientContext } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { type CreateRfq201Response, CreateRfq201ResponseFromJSON } from '@/sdk';

export interface CreateRfqLineInput {
    partNumber: string;
    description?: string;
    method?: string;
    material?: string;
    tolerance?: string;
    finish?: string;
    qty: number;
    uom?: string;
    targetPrice?: number;
    cadDocumentId?: number;
    requiredDate?: string;
}

export interface CreateRfqPayload {
    title: string;
    method: RfqMethod;
    material?: string;
    tolerance?: string;
    finish?: string;
    deliveryLocation?: string;
    incoterm?: string;
    currency?: string;
    paymentTerms?: string;
    taxPercent?: number;
    dueAt?: Date | string;
    notes?: string;
    openBidding?: boolean;
    digitalTwinId?: number;
    items: CreateRfqLineInput[];
}

export interface UseCreateRfqOptions {
    onSuccess?: (response: CreateRfq201Response) => void;
}

export function useCreateRfq(options: UseCreateRfqOptions = {}) {
    const { configuration } = useApiClientContext();
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: CreateRfqPayload) => {
            const fetchApi = configuration.fetchApi ?? fetch;
            const url = `${configuration.basePath.replace(/\/$/, '')}/api/rfqs`;
            const response = await fetchApi(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(serializeCreatePayload(payload)),
            });
            const data = await response.json();
            return CreateRfq201ResponseFromJSON(data);
        },
        onSuccess: (response: CreateRfq201Response) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.root() });
            if (options.onSuccess) {
                options.onSuccess(response);
            }
        },
    });
}

function serializeCreatePayload(payload: CreateRfqPayload) {
    const body: Record<string, unknown> = {
        title: payload.title,
        method: payload.method,
        open_bidding: Boolean(payload.openBidding),
        items: payload.items.map((item) => {
            const normalized: Record<string, unknown> = {
                part_number: item.partNumber,
                qty: item.qty,
            };

            if (item.description) normalized.description = item.description;
            if (item.method) normalized.method = item.method;
            if (item.material) normalized.material = item.material;
            if (item.tolerance) normalized.tolerance = item.tolerance;
            if (item.finish) normalized.finish = item.finish;
            if (item.uom) normalized.uom = item.uom;
            if (item.targetPrice !== undefined)
                normalized.target_price = item.targetPrice;
            if (item.cadDocumentId) normalized.cad_doc_id = item.cadDocumentId;
            if (item.requiredDate) normalized.required_date = item.requiredDate;

            return normalized;
        }),
    };

    if (payload.material) body.material = payload.material;
    if (payload.tolerance) body.tolerance = payload.tolerance;
    if (payload.finish) body.finish = payload.finish;
    if (payload.deliveryLocation)
        body.delivery_location = payload.deliveryLocation;
    if (payload.incoterm) body.incoterm = payload.incoterm;
    if (payload.currency) body.currency = payload.currency;
    if (payload.paymentTerms) body.payment_terms = payload.paymentTerms;
    if (payload.taxPercent !== undefined) body.tax_percent = payload.taxPercent;
    if (payload.notes) body.notes = payload.notes;
    if (payload.digitalTwinId) body.digital_twin_id = payload.digitalTwinId;
    if (payload.dueAt) {
        const value =
            payload.dueAt instanceof Date
                ? payload.dueAt.toISOString()
                : payload.dueAt;
        body.due_at = value;
        body.close_at = value;
    }

    return body;
}
