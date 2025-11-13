import { BaseAPI, HttpError, type Configuration } from '@/sdk';

interface ApiEnvelope<T> {
    status?: string;
    message?: string;
    data?: T;
    errors?: unknown;
}

export interface LoginRequest {
    email: string;
    password: string;
    remember?: boolean;
}

export interface LoginResponse {
    token?: string;
    user?: Record<string, unknown>;
    company?: Record<string, unknown> | null;
    feature_flags?: unknown;
    plan?: unknown;
}

export interface ForgotPasswordRequest {
    email: string;
}

export interface ResetPasswordRequest {
    email: string;
    token: string;
    password: string;
    password_confirmation: string;
}

export interface AuthSessionResponse {
    user?: Record<string, unknown>;
    company?: Record<string, unknown> | null;
    feature_flags?: unknown;
    plan?: unknown;
    token?: string;
}

function unwrapEnvelope<T>(payload: ApiEnvelope<T> | T): T {
    if (payload && typeof payload === 'object' && 'data' in payload) {
        return (payload as ApiEnvelope<T>).data ?? ({} as T);
    }
    return payload as T;
}

async function parseJson<T>(response: Response): Promise<T> {
    const contentType = response.headers.get('content-type') ?? '';
    if (!contentType.includes('application/json')) {
        throw new HttpError(response, await response.text(), 'Expected JSON response body');
    }

    const json = (await response.json()) as ApiEnvelope<T> | T;
    return unwrapEnvelope<T>(json);
}

export class AuthApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async login(payload: LoginRequest): Promise<LoginResponse> {
        const response = await this.request({
            path: '/auth/login',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: payload,
        });

        return parseJson<LoginResponse>(response);
    }

    async current(): Promise<AuthSessionResponse> {
        const response = await this.request({
            path: '/auth/me',
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        return parseJson<AuthSessionResponse>(response);
    }

    async logout(): Promise<void> {
        await this.request({
            path: '/auth/logout',
            method: 'POST',
            headers: {
                Accept: 'application/json',
            },
        });
    }

    async requestPasswordReset(payload: ForgotPasswordRequest): Promise<void> {
        await this.request({
            path: '/auth/forgot-password',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: payload,
        });
    }

    async resetPassword(payload: ResetPasswordRequest): Promise<void> {
        await this.request({
            path: '/auth/reset-password',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: payload,
        });
    }
}
