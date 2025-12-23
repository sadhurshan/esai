import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useForecastReport, useSupplierPerformanceReport } from '@/hooks/api/analytics/use-analytics';
import { api } from '@/lib/api';

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
        },
    });

    function Wrapper({ children }: PropsWithChildren) {
        return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
    }

    return Wrapper;
}

describe('useForecastReport', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('normalizes report payloads and builds the query string', async () => {
        const apiPostMock = vi.spyOn(api, 'post').mockResolvedValue({
            report: {
                series: [
                    {
                        part_id: 7,
                        part_name: 'Widget 7',
                        data: [
                            {
                                date: '2025-01-01',
                                actual: '120',
                                forecast: 110,
                            },
                        ],
                    },
                ],
                table: [
                    {
                        part_id: 7,
                        part_name: 'Widget 7',
                        total_forecast: '110',
                        total_actual: 120,
                        mape: 8.2,
                        mae: 2.3,
                        reorder_point: 45,
                        safety_stock: 15,
                    },
                ],
                aggregates: {
                    total_forecast: '110',
                    total_actual: 120,
                    mape: 8.2,
                    mae: 2.3,
                    avg_daily_demand: 3.6,
                    recommended_reorder_point: 55,
                    recommended_safety_stock: 22,
                },
                filters_used: {
                    start_date: '2025-01-01',
                    end_date: '2025-02-01',
                    bucket: 'weekly',
                    part_ids: [7],
                    category_ids: ['Power'],
                    location_ids: [303],
                },
            },
            summary: {
                summary_markdown: '## Forecast looks strong',
                bullets: [' Maintain buffer ', ''],
                provider: 'openai',
                source: 'llm-service',
            },
        });

        const { result } = renderHook(
            () =>
                useForecastReport(
                    {
                        startDate: '2025-01-01',
                        endDate: '2025-02-01',
                        partIds: [101, '202'],
                        categoryIds: ['Power', ' '],
                        locationIds: ['303', 404],
                    },
                    { enabled: true },
                ),
            {
                wrapper: createWrapper(),
            },
        );

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(apiPostMock).toHaveBeenCalledTimes(1);
        const url = apiPostMock.mock.calls[0]?.[0];
        expect(url).toContain('/v1/analytics/forecast-report?');
        expect(url).toContain('start_date=2025-01-01');
        expect(url).toContain('end_date=2025-02-01');
        expect(url).toContain('part_ids%5B%5D=101');
        expect(url).toContain('part_ids%5B%5D=202');
        expect(url).toContain('location_ids%5B%5D=303');
        expect(url).toContain('location_ids%5B%5D=404');

        expect(result.current.data?.report.series[0]).toMatchObject({
            partId: 7,
            partName: 'Widget 7',
            data: [
                {
                    date: '2025-01-01',
                    actual: 120,
                    forecast: 110,
                },
            ],
        });
        expect(result.current.data?.report.table[0]).toMatchObject({
            totalForecast: 110,
            totalActual: 120,
            mape: 8.2,
            safetyStock: 15,
        });
        expect(result.current.data?.summary).toMatchObject({
            summaryMarkdown: '## Forecast looks strong',
            bullets: ['Maintain buffer'],
            provider: 'openai',
            source: 'llm-service',
        });
    });

    it('falls back to deterministic defaults when the payload is empty', async () => {
        const apiPostMock = vi.spyOn(api, 'post').mockResolvedValue(null);

        const { result } = renderHook(() => useForecastReport(), {
            wrapper: createWrapper(),
        });

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(apiPostMock).toHaveBeenCalledTimes(1);
        expect(result.current.data?.report.table).toEqual([]);
        expect(result.current.data?.report.series).toEqual([]);
        expect(result.current.data?.summary.provider).toBe('deterministic');
        expect(result.current.data?.summary.bullets).toEqual([]);
    });
});

describe('useSupplierPerformanceReport', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('normalizes supplier performance payloads and encodes params', async () => {
        const apiPostMock = vi.spyOn(api, 'post').mockResolvedValue({
            report: {
                series: [
                    {
                        metric_name: 'onTimeDeliveryRate',
                        label: 'On-time',
                        data: [
                            { date: '2025-02-01', value: '0.91' },
                            { date: '2025-02-08', value: 0.87 },
                        ],
                    },
                ],
                table: [
                    {
                        supplier_id: '55',
                        supplier_name: 'Apex Components',
                        on_time_delivery_rate: '0.91',
                        defect_rate: 0.04,
                        lead_time_variance: 1.25,
                        price_volatility: 0.02,
                        service_responsiveness: 0.96,
                        risk_score: '3.5',
                        risk_category: 'watch',
                    },
                ],
                filters_used: {
                    start_date: '2025-02-01',
                    end_date: '2025-03-01',
                    bucket: 'weekly',
                    supplier_id: 55,
                },
            },
            summary: {
                summary_markdown: 'Supplier stable',
                bullets: ['Watch defect rate'],
                provider: 'deterministic',
                source: 'fallback',
            },
        });

        const { result } = renderHook(
            () =>
                useSupplierPerformanceReport({
                    supplierId: 55,
                    startDate: '2025-02-01',
                    endDate: '2025-03-01',
                }),
            {
                wrapper: createWrapper(),
            },
        );

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(apiPostMock).toHaveBeenCalledTimes(1);
        const url = apiPostMock.mock.calls[0]?.[0];
        expect(url).toContain('/v1/analytics/supplier-performance-report?');
        expect(url).toContain('supplier_id=55');
        expect(url).toContain('start_date=2025-02-01');
        expect(url).toContain('end_date=2025-03-01');

        expect(result.current.data?.report.series[0]).toMatchObject({
            metricName: 'onTimeDeliveryRate',
            label: 'On-time',
            data: [
                { date: '2025-02-01', value: 0.91 },
                { date: '2025-02-08', value: 0.87 },
            ],
        });
        expect(result.current.data?.report.table[0]).toMatchObject({
            supplierId: 55,
            supplierName: 'Apex Components',
            onTimeDeliveryRate: 0.91,
            riskScore: 3.5,
            riskCategory: 'watch',
        });
        expect(result.current.data?.summary).toMatchObject({
            summaryMarkdown: 'Supplier stable',
            bullets: ['Watch defect rate'],
        });
    });

    it('does not execute when disabled', async () => {
        const apiPostMock = vi.spyOn(api, 'post').mockResolvedValue({});

        const { result } = renderHook(
            () =>
                useSupplierPerformanceReport(
                    {
                        supplierId: 99,
                    },
                    { enabled: false },
                ),
            {
                wrapper: createWrapper(),
            },
        );

        expect(result.current.isPending).toBe(true);
        expect(apiPostMock).not.toHaveBeenCalled();
    });
});
