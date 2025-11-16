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

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => ({
        login: loginMock,
        register: vi.fn(),
        isAuthenticated: false,
        state: { error: null },
    }),
}));

describe('LoginPage', () => {
    beforeEach(() => {
        mockedNavigate.mockReset();
        loginMock.mockReset();
        loginMock.mockResolvedValue(undefined);
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
});
