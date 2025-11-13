import { Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth } from '@/components/require-auth';
import { AppLayout } from '@/layouts/app-layout';
import { type ReactElement } from 'react';

function Placeholder({ title }: { title: string }): ReactElement {
    // TODO: replace these placeholders with the real module screens defined in /docs/REQUIREMENTS_FULL.md.
    return (
        <div className="flex flex-1 items-center justify-center bg-background p-8 text-center text-muted-foreground">
            <div className="space-y-2">
                <h1 className="text-2xl font-semibold text-foreground">{title}</h1>
                <p className="max-w-md text-sm">
                    This placeholder should be replaced with the real implementation described in /docs/REQUIREMENTS_FULL.md.
                </p>
            </div>
        </div>
    );
}

function AuthPlaceholder({ title }: { title: string }): ReactElement {
    // TODO: replace with the dedicated authentication screen once the React auth UX is implemented.
    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-8 text-center text-muted-foreground">
            <div className="space-y-2">
                <h1 className="text-2xl font-semibold text-foreground">{title}</h1>
                <p className="max-w-md text-sm">
                    Authentication flow UI pending implementation. Consult /docs/REQUIREMENTS_FULL.md for the required forms and API wiring.
                </p>
            </div>
        </div>
    );
}

export function AppRoutes(): ReactElement {
    return (
        <Routes>
            <Route path="/login" element={<AuthPlaceholder title="Sign in" />} />
            <Route path="/register" element={<AuthPlaceholder title="Create account" />} />
            <Route path="/forgot-password" element={<AuthPlaceholder title="Forgot password" />} />
            <Route path="/reset-password/:token" element={<AuthPlaceholder title="Reset password" />} />
            <Route path="/verify-email" element={<AuthPlaceholder title="Verify email" />} />
            <Route element={<RequireAuth />}>
                <Route path="/app" element={<AppLayout />}>
                    <Route index element={<Placeholder title="Dashboard" />} />
                    <Route path="rfqs" element={<Placeholder title="RFQs" />} />
                    <Route path="rfqs/:rfqId" element={<Placeholder title="RFQ Detail" />} />
                    <Route path="quotes" element={<Placeholder title="Quotes" />} />
                    <Route path="purchase-orders" element={<Placeholder title="Purchase Orders" />} />
                    <Route path="purchase-orders/:purchaseOrderId" element={<Placeholder title="Purchase Order Detail" />} />
                    <Route path="invoices" element={<Placeholder title="Invoices" />} />
                    <Route path="inventory" element={<Placeholder title="Inventory" />} />
                    <Route path="assets" element={<Placeholder title="Assets" />} />
                    <Route path="orders" element={<Placeholder title="Orders" />} />
                    <Route path="risk" element={<Placeholder title="Risk & ESG" />} />
                    <Route path="analytics" element={<Placeholder title="Analytics" />} />
                    <Route path="settings" element={<Placeholder title="Settings" />} />
                    <Route path="admin" element={<Placeholder title="Admin Console" />} />
                    <Route path="*" element={<Placeholder title="Page not found" />} />
                </Route>
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
