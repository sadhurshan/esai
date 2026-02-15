import * as runtime from './generated/runtime';

export type DigitalTwinAssetType =
    | 'CAD'
    | 'STEP'
    | 'STL'
    | 'PDF'
    | 'IMAGE'
    | 'DATA'
    | 'OTHER';

export interface DigitalTwinCategoryNode {
    id: number;
    slug: string;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active: boolean;
    children?: DigitalTwinCategoryNode[];
}

export interface DigitalTwinLibraryAsset {
    id: number;
    type?: DigitalTwinAssetType | string | null;
    filename: string;
    path?: string | null;
    mime?: string | null;
    size_bytes?: number | null;
    is_primary: boolean;
    checksum?: string | null;
    created_at?: string | null;
    download_url?: string | null;
}

export interface DigitalTwinLibrarySpec {
    id: number;
    name: string;
    value?: string | null;
    uom?: string | null;
    sort_order?: number | null;
}

export interface DigitalTwinLibraryCategorySummary {
    id: number | null;
    name?: string | null;
    slug?: string | null;
}

export interface DigitalTwinLibraryListItem {
    id: number;
    slug: string;
    title: string;
    summary?: string | null;
    version?: string | null;
    tags?: string[];
    category?: DigitalTwinLibraryCategorySummary | null;
    thumbnail_url?: string | null;
    primary_asset?: DigitalTwinLibraryAsset | null;
    asset_types?: string[] | null;
    published_at?: string | null;
    updated_at?: string | null;
}

export interface DigitalTwinLibraryDetail extends DigitalTwinLibraryListItem {
    revision_notes?: string | null;
    specs: DigitalTwinLibrarySpec[];
    assets: DigitalTwinLibraryAsset[];
}

export interface DigitalTwinLibraryCursorMeta {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    per_page?: number | null;
}

export interface DigitalTwinLibraryIndexResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        items: DigitalTwinLibraryListItem[];
        meta?: DigitalTwinLibraryCursorMeta | null;
        categories?: DigitalTwinCategoryNode[];
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface DigitalTwinLibraryDetailResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        digital_twin: DigitalTwinLibraryDetail;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface DigitalTwinUseForRfqDraftLine {
    part_name?: string | null;
    spec?: string | null;
    method?: string | null;
    material?: string | null;
    tolerance?: string | null;
    finish?: string | null;
    quantity?: number | null;
    uom?: string | null;
    target_price?: number | null;
    required_date?: string | null;
}

export interface DigitalTwinUseForRfqDraft {
    source: string;
    digital_twin_id: number;
    title?: string | null;
    summary?: string | null;
    notes?: string | null;
    lines: DigitalTwinUseForRfqDraftLine[];
    specs: DigitalTwinLibrarySpec[];
    attachments: DigitalTwinLibraryAsset[];
}

export interface DigitalTwinUseForRfqResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        digital_twin: DigitalTwinLibraryDetail;
        draft: DigitalTwinUseForRfqDraft;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface ListDigitalTwinsRequest {
    cursor?: string;
    per_page?: number;
    q?: string;
    category_id?: number;
    tag?: string;
    tags?: string[];
    has_asset?: DigitalTwinAssetType | string;
    has_assets?: (DigitalTwinAssetType | string)[];
    updated_from?: string;
    updated_to?: string;
    sort?: 'relevance' | 'updated_at' | 'title';
    include?: 'categories'[];
}

export class DigitalTwinLibraryApi extends runtime.BaseAPI {
    private async applyAuthHeaders(
        headers: runtime.HTTPHeaders,
    ): Promise<void> {
        if (this.configuration && this.configuration.apiKey) {
            const apiKey = await this.configuration.apiKey('X-API-Key');
            if (apiKey) {
                headers['X-API-Key'] = apiKey;
            }
        }

        if (this.configuration && this.configuration.accessToken) {
            const token = this.configuration.accessToken;
            const tokenString = await token('bearerAuth', []);
            if (tokenString) {
                headers['Authorization'] = `Bearer ${tokenString}`;
            }
        }
    }

    private buildQuery(params: ListDigitalTwinsRequest): runtime.HTTPQuery {
        const query: runtime.HTTPQuery = {};

        if (params.cursor) {
            query['cursor'] = params.cursor;
        }

        if (typeof params.per_page === 'number') {
            query['per_page'] = params.per_page;
        }

        if (params.q) {
            query['q'] = params.q;
        }

        if (typeof params.category_id === 'number') {
            query['category_id'] = params.category_id;
        }

        if (params.tag) {
            query['tag'] = params.tag;
        }

        if (params.tags && params.tags.length > 0) {
            query['tags[]'] = params.tags;
        }

        if (params.has_asset) {
            query['has_asset'] = params.has_asset;
        }

        if (params.has_assets && params.has_assets.length > 0) {
            query['has_assets[]'] = params.has_assets;
        }

        if (params.updated_from) {
            query['updated_from'] = params.updated_from;
        }

        if (params.updated_to) {
            query['updated_to'] = params.updated_to;
        }

        if (params.sort) {
            query['sort'] = params.sort;
        }

        if (params.include && params.include.length > 0) {
            query['include[]'] = params.include;
        }

        return query;
    }

    async listDigitalTwinsRaw(
        requestParameters: ListDigitalTwinsRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<DigitalTwinLibraryIndexResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const queryParameters = this.buildQuery(requestParameters);

        const response = await this.request(
            {
                path: `/api/library/digital-twins`,
                method: 'GET',
                headers,
                query: queryParameters,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as DigitalTwinLibraryIndexResponse,
        );
    }

    async listDigitalTwins(
        requestParameters: ListDigitalTwinsRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<DigitalTwinLibraryIndexResponse> {
        const response = await this.listDigitalTwinsRaw(
            requestParameters,
            initOverrides,
        );
        return await response.value();
    }

    async getDigitalTwinRaw(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<DigitalTwinLibraryDetailResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/library/digital-twins/${encodeURIComponent(String(digitalTwinId))}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as DigitalTwinLibraryDetailResponse,
        );
    }

    async getDigitalTwin(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<DigitalTwinLibraryDetailResponse> {
        const response = await this.getDigitalTwinRaw(
            digitalTwinId,
            initOverrides,
        );
        return await response.value();
    }

    async useDigitalTwinForRfqRaw(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<DigitalTwinUseForRfqResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/library/digital-twins/${encodeURIComponent(String(digitalTwinId))}/use-for-rfq`,
                method: 'POST',
                headers,
                body: {},
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as DigitalTwinUseForRfqResponse,
        );
    }

    async useDigitalTwinForRfq(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<DigitalTwinUseForRfqResponse> {
        const response = await this.useDigitalTwinForRfqRaw(
            digitalTwinId,
            initOverrides,
        );
        return await response.value();
    }
}
