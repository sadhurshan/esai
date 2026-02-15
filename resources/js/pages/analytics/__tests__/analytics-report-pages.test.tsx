import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import {
    useForecastReport,
    useSupplierPerformanceReport,
} from '@/hooks/api/analytics/use-analytics';
import { useItems } from '@/hooks/api/inventory/use-items';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { useSuppliers } from '@/hooks/api/useSuppliers';
import { ForecastReportPage } from '@/pages/analytics/forecast-report-page';
import { SupplierPerformancePage } from '@/pages/analytics/supplier-performance-page';

vi.mock('@/contexts/auth-context', () => ({
    useAuth: vi.fn(),
}));

vi.mock('@/contexts/formatting-context', () => ({
    useFormatting: vi.fn(),
}));

vi.mock('@/hooks/api/analytics/use-analytics', () => ({
    useForecastReport: vi.fn(),
    useSupplierPerformanceReport: vi.fn(),
}));

vi.mock('@/hooks/api/inventory/use-items', () => ({
    useItems: vi.fn(),
}));

vi.mock('@/hooks/api/inventory/use-locations', () => ({
    useLocations: vi.fn(),
}));

vi.mock('@/hooks/api/useSuppliers', () => ({
    useSuppliers: vi.fn(),
}));

vi.mock('@/components/analytics/mini-chart', () => ({
    MiniChart: () => <div data-testid="mini-chart" />,
}));

vi.mock('@/components/analytics/forecast-line-chart', () => ({
    ForecastLineChart: () => <div data-testid="forecast-line-chart" />,
}));

vi.mock('@/components/analytics/performance-multi-chart', () => ({
    PerformanceMultiChart: () => <div data-testid="performance-multi-chart" />,
}));

vi.mock('@/components/analytics/metrics-table', () => ({
    MetricsTable: (props: { rows?: unknown[] }) => (
        <div data-testid="metrics-table">{props.rows?.length ?? 0} rows</div>
    ),
}));

vi.mock('@/components/plan-upgrade-banner', () => ({
    PlanUpgradeBanner: () => <div>PlanUpgradeBanner</div>,
}));

vi.mock('@/components/empty-state', () => ({
    EmptyState: ({
        title,
        description,
    }: {
        title: string;
        description?: string;
    }) => (
        <div>
            <h3>{title}</h3>
            {description ? <p>{description}</p> : null}
        </div>
    ),
}));

const mockUseAuth = vi.mocked(useAuth);
const mockUseFormatting = vi.mocked(useFormatting);
const mockUseForecastReport = vi.mocked(useForecastReport);
const mockUseSupplierPerformanceReport = vi.mocked(
    useSupplierPerformanceReport,
);
const mockUseItems = vi.mocked(useItems);
const mockUseLocations = vi.mocked(useLocations);
const mockUseSuppliers = vi.mocked(useSuppliers);

function renderWithProviders(children: ReactNode) {
    return render(
        <HelmetProvider>
            <MemoryRouter initialEntries={['/app/analytics']}>
                {children}
            </MemoryRouter>
        </HelmetProvider>,
    );
}

function renderForecastPage() {
    return renderWithProviders(<ForecastReportPage />);
}

function renderSupplierPage() {
    return renderWithProviders(<SupplierPerformancePage />);
}

beforeEach(() => {
    mockUseFormatting.mockReturnValue({
        locale: 'en-US',
        timezone: 'UTC',
        currency: 'USD',
        displayFx: false,
        rawSettings: {} as never,
        formatNumber: (value: number, options?: { fallback?: string }) => {
            if (!Number.isFinite(value)) {
                return options?.fallback ?? '—';
            }
            return Number(value).toString();
        },
        formatMoney: () => '$0',
        formatDate: (value: string | number) => value.toString(),
    } as ReturnType<typeof useFormatting>);

    mockUseItems.mockReturnValue({
        data: { items: [] },
        isLoading: false,
    } as unknown as ReturnType<typeof useItems>);

    mockUseLocations.mockReturnValue({
        data: { items: [] },
        isLoading: false,
    } as unknown as ReturnType<typeof useLocations>);

    mockUseSuppliers.mockReturnValue({
        data: { items: [] },
        isLoading: false,
    } as unknown as ReturnType<typeof useSuppliers>);

    mockUseForecastReport.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: false,
        isPlaceholderData: false,
        isFetching: false,
        error: null,
        refetch: vi.fn(),
    } as unknown as ReturnType<typeof useForecastReport>);

    mockUseSupplierPerformanceReport.mockReturnValue({
        data: undefined,
        isLoading: false,
        isError: false,
        isPlaceholderData: false,
        isFetching: false,
        error: null,
        refetch: vi.fn(),
    } as unknown as ReturnType<typeof useSupplierPerformanceReport>);
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('ForecastReportPage', () => {
    it('renders the AI summary and aggregates when analytics is enabled', () => {
        mockUseAuth.mockReturnValue({
            hasFeature: () => true,
            notifyPlanLimit: vi.fn(),
            clearPlanLimit: vi.fn(),
        } as unknown as ReturnType<typeof useAuth>);

        mockUseForecastReport.mockReturnValue({
            data: {
                summary: {
                    summaryMarkdown: '## Outlook',
                    bullets: ['Alpha spike detected'],
                    provider: 'openai',
                    source: 'ai-service',
                },
                report: {
                    series: [],
                    table: [
                        {
                            partId: 1,
                            partName: 'Widget A',
                            totalForecast: 100,
                            totalActual: 120,
                            mape: 5,
                            mae: 1.2,
                            reorderPoint: 40,
                            safetyStock: 15,
                        },
                    ],
                    aggregates: {
                        totalForecast: 100,
                        totalActual: 120,
                        mape: 5,
                        mae: 1.2,
                        avgDailyDemand: 4,
                        recommendedReorderPoint: 42,
                        recommendedSafetyStock: 21,
                    },
                    filtersUsed: {
                        startDate: '2025-01-01',
                        endDate: '2025-03-01',
                        bucket: 'daily',
                        partIds: [],
                        categoryIds: [],
                        locationIds: [],
                    },
                },
            },
            isLoading: false,
            isError: false,
            isPlaceholderData: false,
            isFetching: false,
            error: null,
            refetch: vi.fn(),
        } as unknown as ReturnType<typeof useForecastReport>);

        renderForecastPage();

        expect(screen.getByText('Inventory forecast')).toBeInTheDocument();
        expect(screen.getByText('Demand storyline')).toBeInTheDocument();
        expect(screen.getByText('Alpha spike detected')).toBeInTheDocument();
        expect(screen.getByText('Observed demand')).toBeInTheDocument();
    });

    it('surface a plan upgrade prompt when the feature is disabled', () => {
        const notifyPlanLimit = vi.fn();
        mockUseAuth.mockReturnValue({
            hasFeature: () => false,
            notifyPlanLimit,
            clearPlanLimit: vi.fn(),
        } as unknown as ReturnType<typeof useAuth>);

        renderForecastPage();

        expect(screen.getByText('Forecasting is locked')).toBeInTheDocument();
        expect(screen.getByText('PlanUpgradeBanner')).toBeInTheDocument();
        expect(notifyPlanLimit).toHaveBeenCalledWith(
            expect.objectContaining({ featureKey: 'analytics.access' }),
        );
    });
});

describe('SupplierPerformancePage', () => {
    it('asks the user to pick a supplier when none is selected', () => {
        mockUseAuth.mockReturnValue({
            hasFeature: () => true,
            notifyPlanLimit: vi.fn(),
            clearPlanLimit: vi.fn(),
            activePersona: { type: 'buyer' },
        } as unknown as ReturnType<typeof useAuth>);

        renderSupplierPage();

        expect(
            screen.getByRole('heading', { name: 'Choose a supplier' }),
        ).toBeInTheDocument();
    });

    it('shows the supplier summary for supplier personas', () => {
        mockUseAuth.mockReturnValue({
            hasFeature: () => true,
            notifyPlanLimit: vi.fn(),
            clearPlanLimit: vi.fn(),
            activePersona: {
                type: 'supplier',
                supplier_id: 77,
                supplier_name: 'Axiom Precision',
            },
        } as unknown as ReturnType<typeof useAuth>);

        mockUseSupplierPerformanceReport.mockReturnValue({
            data: {
                summary: {
                    summaryMarkdown: 'Performance is stable',
                    bullets: ['Hitting SLA commitments'],
                    provider: 'deterministic',
                    source: 'fallback',
                },
                report: {
                    series: [
                        {
                            metricName: 'onTimeDeliveryRate',
                            label: 'On-time',
                            data: [{ date: '2025-02-01', value: 0.92 }],
                        },
                    ],
                    table: [
                        {
                            supplierId: 77,
                            supplierName: 'Axiom Precision',
                            onTimeDeliveryRate: 0.92,
                            defectRate: 0.03,
                            leadTimeVariance: 1.3,
                            priceVolatility: 0.02,
                            serviceResponsiveness: 0.95,
                            riskScore: 2.5,
                            riskCategory: 'watch',
                        },
                    ],
                    filtersUsed: {
                        startDate: '2025-01-01',
                        endDate: '2025-03-01',
                        bucket: 'weekly',
                        supplierId: 77,
                    },
                },
            },
            isLoading: false,
            isError: false,
            isPlaceholderData: false,
            isFetching: false,
            error: null,
            refetch: vi.fn(),
        } as unknown as ReturnType<typeof useSupplierPerformanceReport>);

        renderSupplierPage();

        expect(screen.getByText('Supplier performance')).toBeInTheDocument();
        expect(screen.getByText('Performance storyline')).toBeInTheDocument();
        expect(screen.getByText('Hitting SLA commitments')).toBeInTheDocument();
    });
});
