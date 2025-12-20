import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';

import { CopilotChatBubble } from '@/components/ai/CopilotChatBubble';
import { CopilotChatDock } from '@/components/ai/CopilotChatDock';
import { useAuth } from '@/contexts/auth-context';
import { canUseAiCopilot } from '@/lib/ai/ai-gates';

const PORTAL_ID = 'copilot-chat-widget-root';

export function CopilotChatWidget() {
    const { state, activePersona, hasFeature, isAuthenticated } = useAuth();
    const [portalElement, setPortalElement] = useState<HTMLElement | null>(null);

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
