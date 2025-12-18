import { useMutation, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { api, ApiError } from '@/lib/api';

export const MAX_RFP_PROPOSAL_ATTACHMENT_BYTES = 50 * 1024 * 1024; // align with documents deep spec

export interface SubmitRfpProposalInput {
    rfpId: string | number;
    supplierCompanyId?: number | string | null;
    currency: string;
    priceTotal?: number;
    priceTotalMinor?: number;
    leadTimeDays: number;
    approachSummary: string;
    scheduleSummary: string;
    valueAddSummary?: string;
    attachments?: File[];
}

export type SubmitRfpProposalResult = Record<string, unknown>;

export function useSubmitRfpProposal(): UseMutationResult<SubmitRfpProposalResult, ApiError | Error, SubmitRfpProposalInput> {
    const { notifyPlanLimit } = useAuth();

    return useMutation<SubmitRfpProposalResult, ApiError | Error, SubmitRfpProposalInput>({
        mutationFn: async (input) => {
            const rfpId = String(input.rfpId);
            if (!rfpId || rfpId.length === 0) {
                throw new Error('Missing RFP identifier.');
            }

            const formData = new FormData();
            formData.append('currency', input.currency.toUpperCase());
            formData.append('lead_time_days', String(input.leadTimeDays));
            formData.append('approach_summary', input.approachSummary);
            formData.append('schedule_summary', input.scheduleSummary);

            const trimmedValueAdd = input.valueAddSummary?.trim();
            if (trimmedValueAdd) {
                formData.append('value_add_summary', trimmedValueAdd);
            }

            if (typeof input.priceTotal === 'number' && Number.isFinite(input.priceTotal)) {
                formData.append('price_total', input.priceTotal.toString());
            }

            if (typeof input.priceTotalMinor === 'number' && Number.isFinite(input.priceTotalMinor)) {
                formData.append('price_total_minor', String(Math.round(input.priceTotalMinor)));
            }

            if (input.supplierCompanyId) {
                formData.append('supplier_company_id', String(input.supplierCompanyId));
            }

            (input.attachments ?? []).forEach((file) => {
                if (!(file instanceof File)) {
                    return;
                }
                if (file.size > MAX_RFP_PROPOSAL_ATTACHMENT_BYTES) {
                    throw new Error(`${file.name} exceeds the 50 MB attachment limit.`);
                }
                formData.append('attachments[]', file, file.name);
            });

            const response = await api.post<SubmitRfpProposalResult>(`/rfps/${rfpId}/proposals`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            return response.data;
        },
        onSuccess: () => {
            publishToast({
                variant: 'success',
                title: 'Proposal submitted',
                description: 'We notified the buyer and logged your submission.',
            });
        },
        onError: (error) => {
            if (error instanceof ApiError) {
                if (error.status === 402) {
                    notifyPlanLimit({
                        code: 'rfp_proposals',
                        message: error.message ?? 'Upgrade your plan to submit RFP proposals.',
                    });
                }

                publishToast({
                    variant: 'destructive',
                    title: 'Submission failed',
                    description: error.message ?? 'Unable to submit the proposal right now.',
                });
                return;
            }

            publishToast({
                variant: 'destructive',
                title: 'Unexpected error',
                description: error.message ?? 'Unable to submit the proposal right now.',
            });
        },
    });
}
