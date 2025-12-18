import { createPortal } from 'react-dom';
import { type PropsWithChildren, type ReactNode, useEffect, useState } from 'react';

interface HelmetProps {
    children?: ReactNode;
}

export function Helmet({ children }: HelmetProps) {
    const [mountNode] = useState<HTMLElement | null>(() => {
        if (typeof document === 'undefined') {
            return null;
        }

        const node = document.createElement('helmet-fragment');
        node.setAttribute('data-helmet-shim', 'true');
        return node;
    });

    useEffect(() => {
        if (!mountNode) {
            return undefined;
        }

        document.head.appendChild(mountNode);
        return () => {
            document.head.removeChild(mountNode);
        };
    }, [mountNode]);

    if (!mountNode) {
        return null;
    }

    return createPortal(children, mountNode);
}

interface HelmetProviderProps extends PropsWithChildren {
    context?: unknown;
}

export function HelmetProvider({ children }: HelmetProviderProps) {
    return <>{children}</>;
}
