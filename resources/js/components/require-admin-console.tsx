import { Loader2 } from 'lucide-react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';

import { useAuth } from '@/contexts/auth-context';

export function RequireAdminConsole() {
    const { isLoading, isAdmin, canAccessAdminConsole } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return (
            <div className="flex min-h-[60vh] items-center justify-center">
                <Loader2 className="h-6 w-6 animate-spin text-brand-primary" />
            </div>
        );
    }

    if (!isAdmin || !canAccessAdminConsole) {
        return <Navigate to="/app/access-denied" replace state={{ from: location.pathname }} />;
    }

    return <Outlet />;
}
