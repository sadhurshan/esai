import '../css/app.css';

import { AppErrorBoundary } from '@/components/app-error-boundary';
import { AppProviders } from '@/providers/app-providers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { HelmetProvider } from 'react-helmet-async';
import { BrowserRouter } from 'react-router-dom';
import { AppRoutes } from './app-routes';

const container = document.getElementById('app');

if (container === null) {
    throw new Error('Unable to locate DOM mount element for the application shell.');
}

const root = createRoot(container);

root.render(
    <StrictMode>
        <HelmetProvider>
            <BrowserRouter>
                <AppProviders>
                    <AppErrorBoundary>
                        <AppRoutes />
                    </AppErrorBoundary>
                </AppProviders>
            </BrowserRouter>
        </HelmetProvider>
    </StrictMode>,
);
