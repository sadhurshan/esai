import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HelmetProvider } from 'react-helmet-async';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

import { RegisterPage } from '../register-page';

const mockedNavigate = vi.fn();
const registerMock = vi.fn();

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
        register: registerMock,
        state: unauthenticatedState,
        isAuthenticated: false,
    }),
}));

describe('RegisterPage', () => {
    beforeEach(() => {
        mockedNavigate.mockReset();
        registerMock.mockReset();
        registerMock.mockResolvedValue({
            requiresEmailVerification: false,
            requiresPlanSelection: true,
            needsSupplierApproval: false,
            userRole: 'buyer_admin',
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('submits company + owner details and redirects to setup plan', async () => {
        const user = userEvent.setup({ delay: 0 });

        render(
            <HelmetProvider>
                <MemoryRouter initialEntries={[{ pathname: '/register' }]}>
                    <RegisterPage />
                </MemoryRouter>
            </HelmetProvider>,
        );

        await user.type(screen.getByLabelText('Full name'), 'Casey Owner');
        await user.type(screen.getByLabelText('Work email'), 'casey@example.com');
        await user.type(screen.getByLabelText('Company name'), 'Axiom Manufacturing');
        await user.type(screen.getByLabelText('Company domain'), 'axiom.example');
        await user.type(screen.getByLabelText('Registration number'), 'REG-22');
        await user.type(screen.getByLabelText('Tax ID'), 'TAX-22');
        await user.type(screen.getByLabelText('Password'), 'Passw0rd!');
        await user.type(screen.getByLabelText('Confirm password'), 'Passw0rd!');
        await user.type(screen.getByLabelText('Company website'), 'https://axiom.example');
        await user.type(screen.getByLabelText('Country (ISO code)'), 'us');

        const documentFile = new File(['company-doc'], 'registration.pdf', { type: 'application/pdf' });
        await user.upload(screen.getByLabelText('Document file'), documentFile);

        await user.click(screen.getByRole('button', { name: 'Create workspace' }));

        await waitFor(() => expect(registerMock).toHaveBeenCalledTimes(1));

        expect(registerMock).toHaveBeenCalledWith({
            name: 'Casey Owner',
            email: 'casey@example.com',
            password: 'Passw0rd!',
            passwordConfirmation: 'Passw0rd!',
            companyName: 'Axiom Manufacturing',
            companyDomain: 'axiom.example',
            registrationNo: 'REG-22',
            taxId: 'TAX-22',
            website: 'https://axiom.example',
            phone: undefined,
            address: undefined,
            country: 'US',
            companyDocuments: [
                {
                    type: 'registration',
                    file: documentFile,
                },
            ],
            startMode: 'buyer',
        });

        await waitFor(() => expect(mockedNavigate).toHaveBeenCalledWith('/app/setup/plan', { replace: true }));
    }, 10000);
});
