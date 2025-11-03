import { useEffect } from 'react';

const TOAST_EVENT = 'app:toast';

export type ToastPayload = {
    id: string;
    title?: string;
    description?: string;
    variant?: 'default' | 'success' | 'destructive';
};

export type ToastListener = (payload: ToastPayload) => void;

export function publishToast(payload: Omit<ToastPayload, 'id'>) {
    if (typeof window === 'undefined') {
        return;
    }

    const event = new CustomEvent<ToastPayload>(TOAST_EVENT, {
        detail: { id: globalThis.crypto?.randomUUID?.() ?? String(Date.now()), ...payload },
    });

    window.dispatchEvent(event);
}

export function useToastListener(listener: ToastListener) {
    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handler = (event: Event) => {
            const customEvent = event as CustomEvent<ToastPayload>;
            listener(customEvent.detail);
        };

        window.addEventListener(TOAST_EVENT, handler as EventListener);

        return () => {
            window.removeEventListener(TOAST_EVENT, handler as EventListener);
        };
    }, [listener]);
}

export { TOAST_EVENT };
