import { useAuth } from '@/contexts/auth-context';
import { Loader2 } from 'lucide-react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';

export function RequireAuth() {
    const { isAuthenticated, isLoading, requiresEmailVerification } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-background">
                <Loader2 className="text-brand-primary h-6 w-6 animate-spin" />
            </div>
        );
    }

    if (!isAuthenticated) {
        return (
            <Navigate to="/login" replace state={{ from: location.pathname }} />
        );
    }

    if (requiresEmailVerification) {
        return <Navigate to="/verify-email" replace />;
    }

    return <Outlet />;
}
