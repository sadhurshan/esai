import { useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';

import { CopilotChatBubble } from '@/components/ai/CopilotChatBubble';
import { CopilotChatDock } from '@/components/ai/CopilotChatDock';
import { useAuth } from '@/contexts/auth-context';
import { useCopilotWidget } from '@/contexts/copilot-widget-context';
import { canUseAiCopilot } from '@/lib/ai/ai-gates';
import {
    COPILOT_DRAFT_REJECT_EVENT,
    COPILOT_TOOL_ERROR_EVENT,
} from '@/lib/copilot-events';

const PORTAL_ID = 'copilot-chat-widget-root';

export function CopilotChatWidget() {
    const { state, activePersona, hasFeature, isAuthenticated } = useAuth();
    const { isOpen, incrementToolErrors, incrementDraftRejects, resetErrors } =
        useCopilotWidget();

    const gate = useMemo(
        () =>
            canUseAiCopilot({
                isAuthenticated,
                authState: state,
                hasFeature,
                activePersona,
            }),
        [activePersona, hasFeature, isAuthenticated, state],
    );

    useEffect(() => {
        if (!gate.allowed) {
            console.info('[copilot] widget gated', {
                reason: gate.reason ?? 'unknown',
                isAuthenticated,
                role: state.user?.role ?? null,
                persona: activePersona?.type ?? null,
                featureFlags: state.featureFlags,
            });
        }
    }, [activePersona?.type, gate.allowed, gate.reason, isAuthenticated, state.featureFlags, state.user?.role]);

    const portalElement = useMemo(() => {
        if (typeof document === 'undefined') {
            return null;
        }

        let element = document.getElementById(PORTAL_ID) as HTMLElement | null;

        if (!element) {
            element = document.createElement('div');
            element.id = PORTAL_ID;
            document.body.appendChild(element);
            element.dataset.copilotWidgetOwned = 'true';
        }

        return element;
    }, []);

    useEffect(() => {
        return () => {
            if (
                portalElement?.dataset.copilotWidgetOwned === 'true' &&
                portalElement.parentElement
            ) {
                portalElement.parentElement.removeChild(portalElement);
            }
        }; 
    }, [portalElement]);

    useEffect(() => {
        if (isOpen) {
            resetErrors();
        }
    }, [isOpen, resetErrors]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleToolError = () => incrementToolErrors();
        const handleDraftReject = () => incrementDraftRejects();

        window.addEventListener(COPILOT_TOOL_ERROR_EVENT, handleToolError);
        window.addEventListener(COPILOT_DRAFT_REJECT_EVENT, handleDraftReject);

        // Manual verification: run `window.dispatchEvent(new CustomEvent('copilot:tool_error'))` in devtools
        // and confirm the badge increments while the widget stays closed.

        return () => {
            window.removeEventListener(
                COPILOT_TOOL_ERROR_EVENT,
                handleToolError,
            );
            window.removeEventListener(
                COPILOT_DRAFT_REJECT_EVENT,
                handleDraftReject,
            );
            resetErrors();
        };
    }, [incrementToolErrors, incrementDraftRejects, resetErrors]);

    if (!gate.allowed || !portalElement) {
        return null;
    }

    return createPortal(
        <>
            <CopilotChatBubble />
            <CopilotChatDock />
        </>,
        portalElement,
    );
}
