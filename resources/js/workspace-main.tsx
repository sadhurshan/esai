import '../css/app.css';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import WorkspaceApp from './workspace-app';

const container = document.getElementById('spa-root') ?? document.getElementById('app');

if (!container) {
    throw new Error('Unable to locate DOM mount element for the workspace shell.');
}

const root = createRoot(container);

root.render(
    <StrictMode>
        <WorkspaceApp />
    </StrictMode>,
);
