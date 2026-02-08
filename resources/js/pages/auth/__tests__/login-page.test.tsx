import { render } from '@testing-library/react';
import { screen, waitFor } from '@testing-library/dom';
import userEvent from '@testing-library/user-event';
import { HelmetProvider } from 'react-helmet-async';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

import { LoginPage } from '../login-page';

const mockedNavigate = vi.fn();
const loginMock = vi.fn();

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');

    return {
        ...actual,
        useNavigate: () => mockedNavigate,
    };
});

const unauthenticatedState = {
    status: 'unauthenticated' as const,
    error: null,
    requiresEmailVerification: false,
    requiresPlanSelection: false,
    needsSupplierApproval: false,
    company: null,
};

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        login: loginMock,
        register: vi.fn(),
        isAuthenticated: false,
        state: unauthenticatedState,
    }),
}));

describe('LoginPage', () => {
    beforeEach(() => {
        mockedNavigate.mockReset();
        loginMock.mockReset();
        loginMock.mockResolvedValue({
            requiresEmailVerification: false,
            requiresPlanSelection: false,
            needsSupplierApproval: false,
            userRole: 'buyer_admin',
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('submits credentials and redirects to /app on success', async () => {
        const user = userEvent.setup();

        render(
            <HelmetProvider>
                <MemoryRouter initialEntries={[{ pathname: '/login' }] }>
                    <LoginPage />
                </MemoryRouter>
            </HelmetProvider>,
        );

        await user.type(screen.getByLabelText('Email'), 'buyer@example.com');
        await user.type(screen.getByLabelText('Password'), 'super-secret');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        await waitFor(() =>
            expect(loginMock).toHaveBeenCalledWith({
                email: 'buyer@example.com',
                password: 'super-secret',
                remember: true,
            }),
        );

        await waitFor(() => expect(mockedNavigate).toHaveBeenCalledWith('/app', { replace: true }));
    });

    it('redirects platform operators to the admin console when no explicit target is provided', async () => {
        loginMock.mockResolvedValueOnce({
            requiresEmailVerification: false,
            requiresPlanSelection: false,
            needsSupplierApproval: false,
            userRole: 'platform_super',
        });

        const user = userEvent.setup();

        render(
            <HelmetProvider>
                <MemoryRouter initialEntries={[{ pathname: '/login' }] }>
                    <LoginPage />
                </MemoryRouter>
            </HelmetProvider>,
        );

        await user.type(screen.getByLabelText('Email'), 'platform@example.com');
        await user.type(screen.getByLabelText('Password'), 'super-secret');
        await user.click(screen.getByRole('button', { name: 'Sign in' }));

        await waitFor(() =>
            expect(loginMock).toHaveBeenCalledWith({
                email: 'platform@example.com',
                password: 'super-secret',
                remember: true,
            }),
        );

        await waitFor(() => expect(mockedNavigate).toHaveBeenCalledWith('/app/admin', { replace: true }));
    });
});
