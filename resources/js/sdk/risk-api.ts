import type { Configuration } from '../../sdk/ts-client/generated';
import type {
    HTTPHeaders,
    InitOverrideFunction,
} from '../../sdk/ts-client/generated/runtime';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';

import { parseEnvelope, sanitizeQuery } from './api-helpers';

export interface ListRiskScoresQuery extends Record<string, unknown> {
    grade?: 'low' | 'medium' | 'high';
    from?: string;
    to?: string;
}

export interface GenerateRiskScoresPayload extends Record<string, unknown> {
    year?: number;
    month?: number;
}

export class RiskModuleApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async listScores(
        query: ListRiskScoresQuery = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/risk',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    grade: query.grade,
                    from: query.from,
                    to: query.to,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async showScore(
        supplierId: string | number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/risk/${encodeURIComponent(String(supplierId))}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async generateScores(
        payload: GenerateRiskScoresPayload = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ) {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/risk/generate',
                method: 'POST',
                headers,
                body: {
                    year: payload.year,
                    month: payload.month,
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }
}
