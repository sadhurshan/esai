import { BrowserRouter } from 'react-router-dom';
import { AppProviders } from '@/providers/app-providers';
import { AppRoutes } from '@/routes/workspace/app-routes';

export default function WorkspaceApp() {
    return (
        <AppProviders>
            <BrowserRouter>
                <AppRoutes />
            </BrowserRouter>
        </AppProviders>
    );
}
