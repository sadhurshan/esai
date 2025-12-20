import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CopilotChatWidget } from '@/components/ai/CopilotChatWidget';
import { CopilotWidgetProvider } from '@/contexts/copilot-widget-context';

type MockAuthUser = {
    role?: string | null;
    permissions?: string[];
};

type MockAuthState = {
    status: 'idle' | 'loading' | 'authenticated' | 'unauthenticated';
    token: string | null;
    user: MockAuthUser;
    featureFlags: Record<string, boolean>;
    plan: string | null;
};

type MockAuthHook = {
    state: MockAuthState;
    activePersona: { type?: string | null; role?: string | null } | null;
    hasFeature: (key: string) => boolean;
    isAuthenticated: boolean;
};

let mockAuth: MockAuthHook;

const buildAuth = (overrides: Partial<MockAuthHook> = {}): MockAuthHook => {
    const baseState: MockAuthState = {
        status: 'authenticated',
        token: 'token',
        user: {
            role: 'buyer_admin',
            permissions: ['ai.workflows.run'],
        },
        featureFlags: {
            ai_workflows_enabled: true,
        },
        plan: 'growth',
    };

    const stateOverride: Partial<MockAuthState> = overrides.state ?? {};

    return {
        state: {
            ...baseState,
            ...stateOverride,
            user: {
                ...baseState.user,
                ...(stateOverride.user ?? {}),
            },
            featureFlags: {
                ...baseState.featureFlags,
                ...(stateOverride.featureFlags ?? {}),
            },
        },
        activePersona: overrides.activePersona ?? { type: 'buyer', role: 'buyer_admin' },
        hasFeature: overrides.hasFeature ?? (() => true),
        isAuthenticated: overrides.isAuthenticated ?? true,
    };
};

vi.mock('@/contexts/auth-context', () => ({
    useAuth: () => mockAuth,
}));

vi.mock('@/components/ai/CopilotActionsPanel', () => ({
    CopilotActionsPanel: ({ className }: { className?: string }) => (
        <div data-testid="copilot-actions-panel" className={className}>
            Copilot content
        </div>
    ),
}));

describe('CopilotChatWidget', () => {
    beforeEach(() => {
        mockAuth = buildAuth();
        window.localStorage.clear();
    });

    function renderWidget() {
        return render(
            <CopilotWidgetProvider>
                <CopilotChatWidget />
            </CopilotWidgetProvider>,
        );
    }

    it('renders the chat bubble when AI access is allowed', () => {
        renderWidget();
        expect(screen.getByRole('button', { name: /ai copilot/i })).toBeInTheDocument();
    });

    it('hides the widget when the AI feature is disabled', () => {
        mockAuth.hasFeature = () => false;
        mockAuth.state.featureFlags = {};

        renderWidget();

        expect(screen.queryByRole('button', { name: /ai copilot/i })).not.toBeInTheDocument();
    });

    it('opens and closes the dock via the bubble and Escape key', async () => {
        renderWidget();

        const bubble = screen.getByRole('button', { name: /ai copilot/i });
        fireEvent.click(bubble);

        expect(screen.getByText('AI Copilot')).toBeInTheDocument();
        expect(screen.getByTestId('copilot-actions-panel')).toBeInTheDocument();

        fireEvent.keyDown(document, { key: 'Escape' });

        await waitFor(() => {
            expect(screen.queryByText('AI Copilot')).not.toBeInTheDocument();
        });
    });
});
