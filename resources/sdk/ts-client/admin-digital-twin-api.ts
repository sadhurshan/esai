import * as runtime from './generated/runtime';

export type DigitalTwinStatus = 'draft' | 'published' | 'archived';

export interface AdminDigitalTwinCategorySummary {
    id: number | null;
    name?: string | null;
    slug?: string | null;
}

export interface AdminDigitalTwinSpecPayload {
    id?: number;
    name: string;
    value: string;
    uom?: string | null;
    sort_order?: number | null;
}

export interface AdminDigitalTwinMutationPayload {
    category_id?: number | null;
    code?: string | null;
    title?: string;
    summary?: string | null;
    version?: string | null;
    revision_notes?: string | null;
    visibility?: string | null;
    thumbnail_path?: string | null;
    tags?: string[];
    specs?: AdminDigitalTwinSpecPayload[];
}

export interface AdminDigitalTwinSpec {
    id: number;
    name: string;
    value?: string | null;
    uom?: string | null;
    sort_order?: number | null;
}

export interface AdminDigitalTwinAsset {
    id: number;
    type?: string | null;
    filename: string;
    path?: string | null;
    mime?: string | null;
    size_bytes?: number | null;
    is_primary: boolean;
    checksum?: string | null;
    created_at?: string | null;
    download_url?: string | null;
}

export interface AdminDigitalTwinAuditEventActor {
    id: number;
    name?: string | null;
    email?: string | null;
}

export interface AdminDigitalTwinAuditEvent {
    id: number;
    event: string;
    meta?: Record<string, unknown> | null;
    actor?: AdminDigitalTwinAuditEventActor | null;
    created_at?: string | null;
}

export interface AdminDigitalTwinListItem {
    id: number;
    slug: string;
    code?: string | null;
    title: string;
    summary?: string | null;
    status?: DigitalTwinStatus | string | null;
    version?: string | null;
    revision_notes?: string | null;
    tags?: string[];
    category?: AdminDigitalTwinCategorySummary | null;
    thumbnail_path?: string | null;
    published_at?: string | null;
    archived_at?: string | null;
    updated_at?: string | null;
    created_at?: string | null;
}

export interface AdminDigitalTwinDetail extends AdminDigitalTwinListItem {
    visibility?: string | null;
    specs: AdminDigitalTwinSpec[];
    assets: AdminDigitalTwinAsset[];
}

export interface CursorMetaResponse {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    per_page?: number | null;
}

export interface AdminDigitalTwinIndexResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        items: AdminDigitalTwinListItem[];
        meta?: CursorMetaResponse | null;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface AdminDigitalTwinDetailResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        digital_twin: AdminDigitalTwinDetail;
    } | null;
    errors?: Record<string, unknown> | null;
}

export type AdminDigitalTwinMutationResponse = AdminDigitalTwinDetailResponse;

export interface AdminDigitalTwinAssetResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        asset: AdminDigitalTwinAsset;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface AdminDigitalTwinAuditEventResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        items: AdminDigitalTwinAuditEvent[];
        meta?: CursorMetaResponse | null;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface AdminDigitalTwinCategoryNode {
    id: number;
    slug: string;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active: boolean;
    children?: AdminDigitalTwinCategoryNode[];
    created_at?: string | null;
    updated_at?: string | null;
}

export interface AdminDigitalTwinCategoryResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        category: AdminDigitalTwinCategoryNode;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface AdminDigitalTwinCategoryListResponse {
    status: 'success' | 'error';
    message?: string | null;
    data: {
        items: AdminDigitalTwinCategoryNode[];
        meta?: CursorMetaResponse | null;
    } | null;
    errors?: Record<string, unknown> | null;
}

export interface ListAdminDigitalTwinsRequest {
    cursor?: string;
    per_page?: number;
    status?: string;
    category_id?: number;
    q?: string;
}

export interface ListAdminDigitalTwinCategoriesRequest {
    cursor?: string;
    per_page?: number;
    tree?: boolean;
}

export interface ListAdminDigitalTwinAuditEventsRequest {
    cursor?: string;
    per_page?: number;
}

export interface AdminDigitalTwinCategoryPayload {
    name: string;
    slug?: string;
    description?: string | null;
    parent_id?: number | null;
    is_active?: boolean;
}

export interface UploadDigitalTwinAssetParams {
    file: Blob;
    type?: string;
    is_primary?: boolean;
}

export class AdminDigitalTwinApi extends runtime.BaseAPI {
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

    private buildQuery(
        params: ListAdminDigitalTwinsRequest,
    ): runtime.HTTPQuery {
        const query: runtime.HTTPQuery = {};

        if (params.cursor) {
            query['cursor'] = params.cursor;
        }

        if (typeof params.per_page === 'number') {
            query['per_page'] = params.per_page;
        }

        if (params.status) {
            query['status'] = params.status;
        }

        if (typeof params.category_id === 'number') {
            query['category_id'] = params.category_id;
        }

        if (params.q) {
            query['q'] = params.q;
        }

        return query;
    }

    async listDigitalTwinsRaw(
        requestParameters: ListAdminDigitalTwinsRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinIndexResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const queryParameters = this.buildQuery(requestParameters);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins`,
                method: 'GET',
                headers,
                query: queryParameters,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinIndexResponse,
        );
    }

    async listDigitalTwins(
        requestParameters: ListAdminDigitalTwinsRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinIndexResponse> {
        const response = await this.listDigitalTwinsRaw(
            requestParameters,
            initOverrides,
        );
        return await response.value();
    }

    async getDigitalTwinRaw(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinDetailResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinDetailResponse,
        );
    }

    async getDigitalTwin(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinDetailResponse> {
        const response = await this.getDigitalTwinRaw(
            digitalTwinId,
            initOverrides,
        );
        return await response.value();
    }

    async createDigitalTwinRaw(
        payload: AdminDigitalTwinMutationPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinMutationResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins`,
                method: 'POST',
                headers,
                body: payload,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinMutationResponse,
        );
    }

    async createDigitalTwin(
        payload: AdminDigitalTwinMutationPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinMutationResponse> {
        const response = await this.createDigitalTwinRaw(
            payload,
            initOverrides,
        );
        return await response.value();
    }

    async updateDigitalTwinRaw(
        digitalTwinId: number | string,
        payload: AdminDigitalTwinMutationPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinMutationResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}`,
                method: 'PATCH',
                headers,
                body: payload,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinMutationResponse,
        );
    }

    async updateDigitalTwin(
        digitalTwinId: number | string,
        payload: AdminDigitalTwinMutationPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinMutationResponse> {
        const response = await this.updateDigitalTwinRaw(
            digitalTwinId,
            payload,
            initOverrides,
        );
        return await response.value();
    }

    async deleteDigitalTwinRaw(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinMutationResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}`,
                method: 'DELETE',
                headers,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinMutationResponse,
        );
    }

    async deleteDigitalTwin(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinMutationResponse> {
        const response = await this.deleteDigitalTwinRaw(
            digitalTwinId,
            initOverrides,
        );
        return await response.value();
    }

    async publishDigitalTwinRaw(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinMutationResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}/publish`,
                method: 'POST',
                headers,
                body: {},
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinMutationResponse,
        );
    }

    async publishDigitalTwin(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinMutationResponse> {
        const response = await this.publishDigitalTwinRaw(
            digitalTwinId,
            initOverrides,
        );
        return await response.value();
    }

    async archiveDigitalTwinRaw(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinMutationResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}/archive`,
                method: 'POST',
                headers,
                body: {},
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinMutationResponse,
        );
    }

    async archiveDigitalTwin(
        digitalTwinId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinMutationResponse> {
        const response = await this.archiveDigitalTwinRaw(
            digitalTwinId,
            initOverrides,
        );
        return await response.value();
    }

    async listDigitalTwinAuditEventsRaw(
        digitalTwinId: number | string,
        requestParameters: ListAdminDigitalTwinAuditEventsRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinAuditEventResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const queryParameters: runtime.HTTPQuery = {};
        if (requestParameters.cursor) {
            queryParameters['cursor'] = requestParameters.cursor;
        }
        if (typeof requestParameters.per_page === 'number') {
            queryParameters['per_page'] = requestParameters.per_page;
        }

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}/audit-events`,
                method: 'GET',
                headers,
                query: queryParameters,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinAuditEventResponse,
        );
    }

    async listDigitalTwinAuditEvents(
        digitalTwinId: number | string,
        requestParameters: ListAdminDigitalTwinAuditEventsRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinAuditEventResponse> {
        const response = await this.listDigitalTwinAuditEventsRaw(
            digitalTwinId,
            requestParameters,
            initOverrides,
        );
        return await response.value();
    }

    async uploadDigitalTwinAssetRaw(
        digitalTwinId: number | string,
        params: UploadDigitalTwinAssetParams,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinAssetResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const formData = new FormData();
        formData.append('file', params.file);

        if (params.type) {
            formData.append('type', params.type);
        }

        if (typeof params.is_primary === 'boolean') {
            formData.append('is_primary', params.is_primary ? '1' : '0');
        }

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}/assets`,
                method: 'POST',
                headers,
                body: formData,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinAssetResponse,
        );
    }

    async uploadDigitalTwinAsset(
        digitalTwinId: number | string,
        params: UploadDigitalTwinAssetParams,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinAssetResponse> {
        const response = await this.uploadDigitalTwinAssetRaw(
            digitalTwinId,
            params,
            initOverrides,
        );
        return await response.value();
    }

    async deleteDigitalTwinAssetRaw(
        digitalTwinId: number | string,
        assetId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinMutationResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twins/${encodeURIComponent(String(digitalTwinId))}/assets/${encodeURIComponent(String(assetId))}`,
                method: 'DELETE',
                headers,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinMutationResponse,
        );
    }

    async deleteDigitalTwinAsset(
        digitalTwinId: number | string,
        assetId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinMutationResponse> {
        const response = await this.deleteDigitalTwinAssetRaw(
            digitalTwinId,
            assetId,
            initOverrides,
        );
        return await response.value();
    }

    private buildCategoryQuery(
        params: ListAdminDigitalTwinCategoriesRequest,
    ): runtime.HTTPQuery {
        const query: runtime.HTTPQuery = {};

        if (params.cursor) {
            query['cursor'] = params.cursor;
        }

        if (typeof params.per_page === 'number') {
            query['per_page'] = params.per_page;
        }

        if (params.tree) {
            query['tree'] = params.tree ? '1' : '0';
        }

        return query;
    }

    async listCategoriesRaw(
        params: ListAdminDigitalTwinCategoriesRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinCategoryListResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const query = this.buildCategoryQuery(params);

        const response = await this.request(
            {
                path: `/api/admin/digital-twin-categories`,
                method: 'GET',
                headers,
                query,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinCategoryListResponse,
        );
    }

    async listCategories(
        params: ListAdminDigitalTwinCategoriesRequest = {},
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinCategoryListResponse> {
        const response = await this.listCategoriesRaw(params, initOverrides);
        return await response.value();
    }

    async createCategoryRaw(
        payload: AdminDigitalTwinCategoryPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinCategoryResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twin-categories`,
                method: 'POST',
                headers,
                body: payload,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinCategoryResponse,
        );
    }

    async createCategory(
        payload: AdminDigitalTwinCategoryPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinCategoryResponse> {
        const response = await this.createCategoryRaw(payload, initOverrides);
        return await response.value();
    }

    async updateCategoryRaw(
        categoryId: number | string,
        payload: AdminDigitalTwinCategoryPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinCategoryResponse>> {
        const headers: runtime.HTTPHeaders = {
            'Content-Type': 'application/json',
        };
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twin-categories/${encodeURIComponent(String(categoryId))}`,
                method: 'PATCH',
                headers,
                body: payload,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinCategoryResponse,
        );
    }

    async updateCategory(
        categoryId: number | string,
        payload: AdminDigitalTwinCategoryPayload,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinCategoryResponse> {
        const response = await this.updateCategoryRaw(
            categoryId,
            payload,
            initOverrides,
        );
        return await response.value();
    }

    async deleteCategoryRaw(
        categoryId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<runtime.ApiResponse<AdminDigitalTwinCategoryResponse>> {
        const headers: runtime.HTTPHeaders = {};
        await this.applyAuthHeaders(headers);

        const response = await this.request(
            {
                path: `/api/admin/digital-twin-categories/${encodeURIComponent(String(categoryId))}`,
                method: 'DELETE',
                headers,
            },
            initOverrides,
        );

        return new runtime.JSONApiResponse(
            response,
            (jsonValue) => jsonValue as AdminDigitalTwinCategoryResponse,
        );
    }

    async deleteCategory(
        categoryId: number | string,
        initOverrides?: RequestInit | runtime.InitOverrideFunction,
    ): Promise<AdminDigitalTwinCategoryResponse> {
        const response = await this.deleteCategoryRaw(
            categoryId,
            initOverrides,
        );
        return await response.value();
    }
}
