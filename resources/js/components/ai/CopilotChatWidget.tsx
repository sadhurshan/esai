import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';

import { CopilotChatBubble } from '@/components/ai/CopilotChatBubble';
import { CopilotChatDock } from '@/components/ai/CopilotChatDock';
import { useCopilotWidget } from '@/contexts/copilot-widget-context';
import { COPILOT_DRAFT_REJECT_EVENT, COPILOT_TOOL_ERROR_EVENT } from '@/lib/copilot-events';
import { useAuth } from '@/contexts/auth-context';
import { canUseAiCopilot } from '@/lib/ai/ai-gates';

const PORTAL_ID = 'copilot-chat-widget-root';

export function CopilotChatWidget() {
    const { state, activePersona, hasFeature, isAuthenticated } = useAuth();
    const [portalElement, setPortalElement] = useState<HTMLElement | null>(null);
    const { isOpen, incrementErrors, resetErrors } = useCopilotWidget();

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
        if (typeof document === 'undefined') {
            return;
        }

        let element = document.getElementById(PORTAL_ID) as HTMLElement | null;
        let created = false;

        if (!element) {
            element = document.createElement('div');
            element.id = PORTAL_ID;
            document.body.appendChild(element);
            created = true;
        }

        setPortalElement(element);

        return () => {
            if (created && element?.parentElement) {
                element.parentElement.removeChild(element);
            }
        };
    }, []);

    useEffect(() => {
        if (isOpen) {
            resetErrors();
        }
    }, [isOpen, resetErrors]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleBadgeIncrement = () => incrementErrors();

        window.addEventListener(COPILOT_TOOL_ERROR_EVENT, handleBadgeIncrement);
        window.addEventListener(COPILOT_DRAFT_REJECT_EVENT, handleBadgeIncrement);

        // Manual verification: run `window.dispatchEvent(new CustomEvent('copilot:tool_error'))` in devtools
        // and confirm the badge increments while the widget stays closed.

        return () => {
            window.removeEventListener(COPILOT_TOOL_ERROR_EVENT, handleBadgeIncrement);
            window.removeEventListener(COPILOT_DRAFT_REJECT_EVENT, handleBadgeIncrement);
            resetErrors();
        };
    }, [incrementErrors, resetErrors]);

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
