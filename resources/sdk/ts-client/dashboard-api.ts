import * as runtime from './generated/runtime';

export interface DashboardMetricsPayload {
    open_rfq_count: number;
    quotes_awaiting_review_count: number;
    pos_awaiting_acknowledgement_count: number;
    unpaid_invoice_count: number;
    low_stock_part_count: number;
}

export interface DashboardMetricsResponse {
    status: 'success';
    message?: string | null;
    data: DashboardMetricsPayload;
    errors?: Record<string, unknown> | null;
}

export class DashboardApi extends runtime.BaseAPI {
    async getMetricsRaw(
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<DashboardMetricsResponse>> {
        const headerParameters: runtime.HTTPHeaders = {};

        if (this.configuration && this.configuration.apiKey) {
            headerParameters['X-API-Key'] =
                await this.configuration.apiKey('X-API-Key');
        }

        if (this.configuration && this.configuration.accessToken) {
            const token = this.configuration.accessToken;
            const tokenString = await token('bearerAuth', []);

            if (tokenString) {
                headerParameters['Authorization'] = `Bearer ${tokenString}`;
            }
        }

        const response = await this.request(
            {
                path: `/api/dashboard/metrics`,
                method: 'GET',
                headers: headerParameters,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as DashboardMetricsResponse,
        );
    }

    async getMetrics(
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<DashboardMetricsResponse> {
        const response = await this.getMetricsRaw(initOverrides);
        return await response.value();
    }
}
