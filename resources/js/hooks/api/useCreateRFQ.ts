import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RFQ } from '@/types/sourcing';

interface RFQCreateResponse {
    id: number;
    number: string;
    item_name: string;
    type: string;
    quantity: number;
    material: string;
    method: string;
    tolerance: string | null;
    finish: string | null;
    client_company: string;
    status: string;
    deadline_at: string | null;
    sent_at: string | null;
    is_open_bidding: boolean;
    notes: string | null;
    cad_path: string | null;
}

export interface CreateRFQInput {
    type: 'ready_made' | 'manufacture';
    itemName: string;
    quantity: number;
    material: string;
    method: string;
    tolerance?: string;
    finish?: string;
    clientCompany: string;
    status?: 'awaiting' | 'open' | 'closed' | 'awarded' | 'cancelled';
    deadlineAt?: string;
    sentAt?: string;
    isOpenBidding?: boolean;
    notes?: string;
    cad?: File | null;
}

const mapRFQ = (payload: RFQCreateResponse): RFQ => ({
    id: payload.id,
    rfqNumber: payload.number,
    title: payload.item_name,
    method: payload.method,
    material: payload.material,
    quantity: payload.quantity,
    dueDate: payload.deadline_at ?? '',
    status: payload.status,
    companyName: payload.client_company,
    openBidding: Boolean(payload.is_open_bidding),
    items: [],
});

export function useCreateRFQ() {
    const queryClient = useQueryClient();

    return useMutation<RFQ, ApiError, CreateRFQInput>({
    mutationFn: async (input: CreateRFQInput) => {
            const formData = new FormData();

            formData.append('type', input.type);
            formData.append('item_name', input.itemName);
            formData.append('quantity', String(input.quantity));
            formData.append('material', input.material);
            formData.append('method', input.method);
            formData.append('client_company', input.clientCompany);
            formData.append('status', input.status ?? 'awaiting');

            if (input.tolerance) {
                formData.append('tolerance', input.tolerance);
            }

            if (input.finish) {
                formData.append('finish', input.finish);
            }

            if (input.deadlineAt) {
                formData.append('deadline_at', input.deadlineAt);
            }

            if (input.sentAt) {
                formData.append('sent_at', input.sentAt);
            }

            if (input.isOpenBidding !== undefined) {
                formData.append('is_open_bidding', input.isOpenBidding ? '1' : '0');
            }

            if (input.notes) {
                formData.append('notes', input.notes);
            }

            if (input.cad) {
                formData.append('cad', input.cad);
            }

            const response = (await api.post<RFQCreateResponse>('/rfqs', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            })) as unknown as RFQCreateResponse;

            return mapRFQ(response);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.root() });
        },
    });
}
