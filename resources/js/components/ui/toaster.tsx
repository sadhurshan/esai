import { useCallback, useEffect, useRef, useState } from 'react';

import {
    Toast,
    ToastClose,
    ToastDescription,
    ToastProvider,
    ToastTitle,
    ToastViewport,
} from './toast';
import { type ToastPayload, useToastListener } from './use-toast';

const TOAST_DURATION = 5000;

export function Toaster() {
    const [toasts, setToasts] = useState<ToastPayload[]>([]);
    const timersRef = useRef<Map<string, number>>(new Map());

    const removeToast = useCallback((id: string) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
        const timer = timersRef.current.get(id);
        if (timer) {
            window.clearTimeout(timer);
            timersRef.current.delete(id);
        }
    }, []);

    const handleToast = useCallback(
        (payload: ToastPayload) => {
            setToasts((current) => [...current, payload]);

            const timer = window.setTimeout(() => {
                removeToast(payload.id);
            }, TOAST_DURATION);

            timersRef.current.set(payload.id, timer);
        },
        [removeToast],
    );

    useToastListener(handleToast);

    useEffect(() => {
        const timers = timersRef.current;

        return () => {
            timers.forEach((timer) => {
                window.clearTimeout(timer);
            });
            timers.clear();
        };
    }, []);

    return (
        <ToastProvider>
            {toasts.map((toast) => (
                <Toast
                    key={toast.id}
                    variant={toast.variant}
                    onOpenChange={(open: boolean) => {
                        if (!open) {
                            removeToast(toast.id);
                            const timer = timersRef.current.get(toast.id);
                            if (timer) {
                                window.clearTimeout(timer);
                                timersRef.current.delete(toast.id);
                            }
                        }
                    }}
                >
                    {toast.title ? <ToastTitle>{toast.title}</ToastTitle> : null}
                    {toast.description ? (
                        <ToastDescription>{toast.description}</ToastDescription>
                    ) : null}
                    <ToastClose />
                </Toast>
            ))}
            <ToastViewport />
        </ToastProvider>
    );
}
