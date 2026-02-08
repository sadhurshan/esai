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
    personas?: unknown;
    active_persona?: unknown;
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

export type RegisterDocumentType = 'registration' | 'tax' | 'esg' | 'other';

export interface RegisterDocumentPayload {
    type: RegisterDocumentType;
    file: File;
}

export interface RegisterRequest {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    company_name: string;
    company_domain: string;
    start_mode: 'buyer' | 'supplier';
    address?: string | null;
    phone?: string | null;
    country?: string | null;
    registration_no?: string | null;
    tax_id?: string | null;
    website?: string | null;
    company_documents?: RegisterDocumentPayload[];
}

export interface AuthSessionResponse {
    user?: Record<string, unknown>;
    company?: Record<string, unknown> | null;
    feature_flags?: unknown;
    plan?: unknown;
    token?: string;
    personas?: unknown;
    active_persona?: unknown;
}

export interface SwitchPersonaRequest {
    key: string;
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
            path: '/api/auth/login',
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
            path: '/api/auth/me',
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        });

        return parseJson<AuthSessionResponse>(response);
    }

    async register(payload: RegisterRequest | FormData): Promise<LoginResponse> {
        const isFormData = typeof FormData !== 'undefined' && payload instanceof FormData;
        const response = await this.request({
            path: '/api/auth/register',
            method: 'POST',
            headers: {
                Accept: 'application/json',
                ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            },
            body: payload,
        });

        return parseJson<LoginResponse>(response);
    }

    async logout(): Promise<void> {
        await this.request({
            path: '/api/auth/logout',
            method: 'POST',
            headers: {
                Accept: 'application/json',
            },
        });
    }

    async switchPersona(payload: SwitchPersonaRequest): Promise<AuthSessionResponse> {
        const response = await this.request({
            path: '/api/auth/persona',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: payload,
        });

        return parseJson<AuthSessionResponse>(response);
    }

    async resendVerificationEmail(): Promise<void> {
        await this.request({
            path: '/email/verification-notification',
            method: 'POST',
            headers: {
                Accept: 'application/json',
            },
        });
    }

    async requestPasswordReset(payload: ForgotPasswordRequest): Promise<void> {
        await this.request({
            path: '/api/auth/forgot-password',
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
            path: '/api/auth/reset-password',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: payload,
        });
    }
}
