import { useCallback, useEffect, useMemo, useReducer, useRef } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { LocalizationApi } from '@/sdk';

interface ConversionRequest {
    key: string;
    quantity: number;
    fromCode?: string | null;
}

interface ConversionEntry {
    value: number | null;
    formatted: string | null;
}

interface ConversionResultMap {
    [key: string]: ConversionEntry | undefined;
}

const DEFAULT_BASE_UOM = 'ea'; // TODO: replace once localization settings expose company-level base UoM.
const MAX_FRACTION_DIGITS = 4;

function normalizeCode(code?: string | null): string | null {
    if (!code) {
        return null;
    }

    return code.trim().toLowerCase();
}

export function useUomConversionHelper() {
    const localizationApi = useSdkClient(LocalizationApi);
    const { hasFeature } = useAuth();

    const isEnabled = hasFeature('localization.uoms.convert');

    const baseUomCode = DEFAULT_BASE_UOM;
    const formatter = useMemo(() => {
        return new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: MAX_FRACTION_DIGITS,
        });
    }, []);

    const factorCacheRef = useRef(new Map<string, number>());

    const formatQuantity = useCallback(
        (value: number) => {
            if (!Number.isFinite(value)) {
                return null;
            }
            return formatter.format(value);
        },
        [formatter],
    );

    const convertMany = useCallback(
        async (requests: ConversionRequest[]): Promise<ConversionResultMap> => {
            if (requests.length === 0) {
                return {};
            }

            const results: ConversionResultMap = {};

            const tasks: Array<Promise<void>> = [];

            for (const request of requests) {
                const quantity = Number(request.quantity);
                if (!Number.isFinite(quantity)) {
                    continue;
                }

                const normalizedCode =
                    normalizeCode(request.fromCode) ?? baseUomCode;

                if (!isEnabled || normalizedCode === baseUomCode) {
                    results[request.key] = {
                        value: quantity,
                        formatted: formatQuantity(quantity),
                    };
                    continue;
                }

                if (!factorCacheRef.current.has(normalizedCode)) {
                    tasks.push(
                        (async () => {
                            try {
                                const response =
                                    await localizationApi.convertQuantity({
                                        convertQuantityRequest: {
                                            qty: 1,
                                            fromCode:
                                                request.fromCode ??
                                                normalizedCode,
                                            toCode: baseUomCode,
                                        },
                                    });
                                const converted = Number(
                                    response.data.qtyConverted,
                                );
                                factorCacheRef.current.set(
                                    normalizedCode,
                                    Number.isFinite(converted)
                                        ? converted
                                        : NaN,
                                );
                            } catch {
                                factorCacheRef.current.set(normalizedCode, NaN);
                            }
                        })(),
                    );
                }
            }

            if (tasks.length > 0) {
                await Promise.all(tasks);
            }

            for (const request of requests) {
                const quantity = Number(request.quantity);
                if (!Number.isFinite(quantity)) {
                    continue;
                }

                const normalizedCode =
                    normalizeCode(request.fromCode) ?? baseUomCode;

                if (!isEnabled || normalizedCode === baseUomCode) {
                    results[request.key] = {
                        value: quantity,
                        formatted: formatQuantity(quantity),
                    };
                    continue;
                }

                const factor = factorCacheRef.current.get(normalizedCode);

                if (factor === undefined || Number.isNaN(factor)) {
                    results[request.key] = {
                        value: null,
                        formatted: null,
                    };
                    continue;
                }

                const converted = quantity * factor;
                results[request.key] = {
                    value: converted,
                    formatted: formatQuantity(converted),
                };
            }

            return results;
        },
        [baseUomCode, formatQuantity, isEnabled, localizationApi],
    );

    return {
        baseUomCode,
        isEnabled,
        convertMany,
        formatQuantity,
    };
}

type BaseQuantityState = {
    formatted: string | null;
    loading: boolean;
};

type BaseQuantityAction =
    | { type: 'reset' }
    | { type: 'start' }
    | { type: 'success'; formatted: string | null }
    | { type: 'failure' };

function baseQuantityReducer(
    state: BaseQuantityState,
    action: BaseQuantityAction,
): BaseQuantityState {
    switch (action.type) {
        case 'reset':
            return state.loading || state.formatted !== null
                ? { formatted: null, loading: false }
                : state;
        case 'start':
            return state.loading
                ? state
                : { formatted: state.formatted, loading: true };
        case 'success':
            return { formatted: action.formatted, loading: false };
        case 'failure':
            return { formatted: null, loading: false };
        default:
            return state;
    }
}

export function useBaseUomQuantity(quantity: unknown, uom?: string | null) {
    const { baseUomCode, convertMany, formatQuantity, isEnabled } =
        useUomConversionHelper();
    const [state, dispatch] = useReducer(baseQuantityReducer, {
        formatted: null,
        loading: false,
    });

    const numericQuantity = Number(quantity);
    const normalizedCode = normalizeCode(uom);
    const quantityIsValid = Number.isFinite(numericQuantity);
    const usesBaseUnit = !normalizedCode || normalizedCode === baseUomCode;
    const needsConversion =
        isEnabled &&
        quantityIsValid &&
        !usesBaseUnit &&
        Boolean(normalizedCode);

    const immediateFormatted = useMemo(() => {
        if (!isEnabled || !quantityIsValid) {
            return null;
        }

        if (usesBaseUnit) {
            return formatQuantity(numericQuantity);
        }

        return null;
    }, [
        formatQuantity,
        isEnabled,
        numericQuantity,
        quantityIsValid,
        usesBaseUnit,
    ]);

    useEffect(() => {
        if (!needsConversion) {
            dispatch({ type: 'reset' });
            return;
        }

        let cancelled = false;
        dispatch({ type: 'start' });

        convertMany([
            {
                key: 'single',
                quantity: numericQuantity,
                fromCode: normalizedCode!,
            },
        ])
            .then((result) => {
                if (cancelled) {
                    return;
                }
                const entry = result.single;
                dispatch({
                    type: 'success',
                    formatted: entry?.formatted ?? null,
                });
            })
            .catch(() => {
                if (cancelled) {
                    return;
                }
                dispatch({ type: 'failure' });
            });

        return () => {
            cancelled = true;
        };
    }, [convertMany, needsConversion, normalizedCode, numericQuantity]);

    const convertedLabel = needsConversion
        ? state.formatted
        : immediateFormatted;
    const isLoading = needsConversion ? state.loading : false;

    return {
        baseUomLabel: baseUomCode.toUpperCase(),
        convertedLabel,
        isEnabled,
        isLoading,
    };
}
