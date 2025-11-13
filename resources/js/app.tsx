import '../css/app.css';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { AppProviders } from '@/providers/app-providers';
import { AppRoutes } from './app-routes';

const container = document.getElementById('app');

if (container === null) {
    throw new Error('Unable to locate DOM mount element for the application shell.');
}

const root = createRoot(container);

root.render(
    <StrictMode>
        <AppProviders>
            <BrowserRouter>
                <AppRoutes />
            </BrowserRouter>
        </AppProviders>
    </StrictMode>,
);
