export type AiAnalyticsMetric =
    | 'cycle_time'
    | 'otif'
    | 'response_rate'
    | 'spend'
    | 'forecast_accuracy'
    | 'forecast_spend'
    | 'forecast_supplier_performance'
    | 'forecast_inventory';

export interface AiAnalyticsChartDatum {
    label: string;
    value: number;
}

export interface AiAnalyticsCitation {
    id?: string | number;
    label: string;
    source?: string | null;
    url?: string | null;
}

export interface AiAnalyticsCardPayload {
    metric: AiAnalyticsMetric;
    title: string;
    chartData: AiAnalyticsChartDatum[];
    summary?: string | null;
    citations?: AiAnalyticsCitation[];
}
