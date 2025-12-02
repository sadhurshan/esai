import { keepPreviousData, useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { HttpError, RiskModuleApi, type GenerateRiskScoresPayload, type ListRiskScoresQuery } from '@/sdk';
import { queryKeys } from '@/lib/queryKeys';
import type { SupplierRiskScore } from '@/types/risk';

interface RiskScoresResponse {
    data?: unknown;
    items?: unknown;
    meta?: Record<string, unknown> | null;
}

interface UseRiskScoresResult {
    scores: SupplierRiskScore[];
    meta: Record<string, unknown> | null;
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;
const RISK_GRADES = new Set(['low', 'medium', 'high']);

const toNumber = (value: unknown): number | null => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
};

function mapRiskScore(payload: Record<string, unknown>): SupplierRiskScore {
    const metaRaw = isRecord(payload.meta) ? payload.meta : null;

    const normalizedMeta = metaRaw
        ? {
              ...metaRaw,
              periodKey:
                  typeof metaRaw.periodKey === 'string'
                      ? metaRaw.periodKey
                      : typeof metaRaw.period_key === 'string'
                        ? metaRaw.period_key
                        : null,
              periodStart:
                  typeof metaRaw.periodStart === 'string'
                      ? metaRaw.periodStart
                      : typeof metaRaw.period_start === 'string'
                        ? metaRaw.period_start
                        : null,
              periodEnd:
                  typeof metaRaw.periodEnd === 'string'
                      ? metaRaw.periodEnd
                      : typeof metaRaw.period_end === 'string'
                        ? metaRaw.period_end
                        : null,
          }
        : null;

    const badgesRaw = Array.isArray(payload.badges)
        ? payload.badges.filter((badge): badge is string => typeof badge === 'string')
        : [];

    return {
        supplierId: Number(payload.supplier_id ?? payload.supplierId ?? 0) || 0,
        supplierName: typeof payload.supplier_name === 'string' ? payload.supplier_name : null,
        riskGrade:
            typeof payload.risk_grade === 'string' && RISK_GRADES.has(payload.risk_grade)
                ? (payload.risk_grade as SupplierRiskScore['riskGrade'])
                : null,
        overallScore: toNumber(payload.overall_score),
        onTimeDeliveryRate: toNumber(payload.on_time_delivery_rate),
        defectRate: toNumber(payload.defect_rate),
        priceVolatility: toNumber(payload.price_volatility),
        leadTimeVolatility: toNumber(payload.lead_time_volatility),
        responsivenessRate: toNumber(payload.responsiveness_rate),
        badges: badgesRaw,
        meta: normalizedMeta,
        createdAt: typeof payload.created_at === 'string' ? payload.created_at : null,
        updatedAt: typeof payload.updated_at === 'string' ? payload.updated_at : null,
    } satisfies SupplierRiskScore;
}

function extractScores(payload: RiskScoresResponse | unknown): { rows: SupplierRiskScore[]; meta: Record<string, unknown> | null } {
    if (Array.isArray(payload)) {
        return {
            rows: payload.filter(isRecord).map(mapRiskScore),
            meta: null,
        };
    }

    if (!payload || typeof payload !== 'object') {
        return { rows: [], meta: null };
    }

    const record = payload as RiskScoresResponse;
    const rowsSource = Array.isArray(record.items)
        ? record.items
        : Array.isArray(record.data)
          ? record.data
          : [];

    const rows = rowsSource.filter(isRecord).map(mapRiskScore);

    return {
        rows,
        meta: record.meta ?? null,
    };
}

interface UseRiskScoresOptions {
    enabled?: boolean;
}

export function useRiskScores(
    params: ListRiskScoresQuery = {},
    options: UseRiskScoresOptions = {},
): UseQueryResult<UseRiskScoresResult, HttpError | Error> {
    const riskApi = useSdkClient(RiskModuleApi);

    return useQuery<RiskScoresResponse | unknown, HttpError | Error, UseRiskScoresResult>({
        queryKey: queryKeys.risk.list(params),
        placeholderData: keepPreviousData,
        staleTime: 60_000,
        enabled: options.enabled ?? true,
        queryFn: async () => riskApi.listScores(params),
        select: (response) => {
            const { rows, meta } = extractScores(response);
            return { scores: rows, meta } satisfies UseRiskScoresResult;
        },
    });
}

export function useGenerateRiskScores(): UseMutationResult<
    SupplierRiskScore[],
    HttpError | Error,
    GenerateRiskScoresPayload | undefined
> {
    const riskApi = useSdkClient(RiskModuleApi);
    const queryClient = useQueryClient();

    return useMutation<SupplierRiskScore[], HttpError | Error, GenerateRiskScoresPayload | undefined>({
        mutationFn: async (payload) => {
            const response = await riskApi.generateScores(payload ?? {});
            const { rows } = extractScores(response);
            return rows;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.risk.root() });
        },
    });
}
