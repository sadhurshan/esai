import { Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth } from '@/components/require-auth';
import { AppLayout } from '@/layouts/app-layout';
import { DashboardPage } from '@/pages/dashboard';
import { RfqDetailPage, RfqListPage } from '@/pages/rfqs';
import { QuoteListPage } from '@/pages/quotes';
import { PurchaseOrderDetailPage, PurchaseOrderListPage } from '@/pages/purchase-orders';
import { InvoiceListPage } from '@/pages/invoices';
import { InventoryPage } from '@/pages/inventory';
import { AssetsPage } from '@/pages/assets';
import { OrdersPage } from '@/pages/orders';
import { RiskPage } from '@/pages/risk';
import { AnalyticsPage } from '@/pages/analytics';
import { SettingsPage } from '@/pages/settings';
import { AdminConsolePage } from '@/pages/admin';
import { LoginPage, ForgotPasswordPage, ResetPasswordPage, VerifyEmailPage } from '@/pages/auth';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { NotFoundPage } from '@/pages/errors/not-found-page';
import { type ReactElement } from 'react';

export function AppRoutes(): ReactElement {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password/:token" element={<ResetPasswordPage />} />
            <Route path="/verify-email" element={<VerifyEmailPage />} />
            <Route element={<RequireAuth />}>
                <Route path="/app" element={<AppLayout />}>
                    <Route index element={<DashboardPage />} />
                    <Route path="rfqs" element={<RfqListPage />} />
                    <Route path="rfqs/:rfqId" element={<RfqDetailPage />} />
                    <Route path="quotes" element={<QuoteListPage />} />
                    <Route path="purchase-orders" element={<PurchaseOrderListPage />} />
                    <Route path="purchase-orders/:purchaseOrderId" element={<PurchaseOrderDetailPage />} />
                    <Route path="invoices" element={<InvoiceListPage />} />
                    <Route path="inventory" element={<InventoryPage />} />
                    <Route path="assets" element={<AssetsPage />} />
                    <Route path="orders" element={<OrdersPage />} />
                    <Route path="risk" element={<RiskPage />} />
                    <Route path="analytics" element={<AnalyticsPage />} />
                    <Route path="settings" element={<SettingsPage />} />
                    <Route path="admin" element={<AdminConsolePage />} />
                    <Route path="access-denied" element={<AccessDeniedPage />} />
                    <Route path="*" element={<NotFoundPage />} />
                </Route>
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
