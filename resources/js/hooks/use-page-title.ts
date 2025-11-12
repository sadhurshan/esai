import { useEffect } from 'react';

const DEFAULT_TITLE = import.meta.env.VITE_APP_NAME ?? 'Elements Supply AI';

export function usePageTitle(title: string) {
    useEffect(() => {
        if (!title) {
            document.title = DEFAULT_TITLE;
            return;
        }

        document.title = `${title} · ${DEFAULT_TITLE}`;
    }, [title]);
}
