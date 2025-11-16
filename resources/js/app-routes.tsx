import { Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth } from '@/components/require-auth';
import { RequireSupplierAccess } from '@/components/require-supplier-access';
import { RequireActivePlan } from '@/components/require-active-plan';
import { RequireAdminConsole } from '@/components/require-admin-console';
import { AppLayout } from '@/layouts/app-layout';
import { DashboardPage } from '@/pages/dashboard';
import { RfqCreateWizard, RfqDetailPage, RfqListPage } from '@/pages/rfqs';
import { AwardReviewPage } from '@/pages/awards/award-review-page';
import {
    QuoteDetailPage,
    QuoteListPage,
    SupplierQuoteCreatePage,
    SupplierQuoteEditPage,
} from '@/pages/quotes';
import { PoDetailPage, PoListPage } from '@/pages/pos';
import { InvoiceDetailPage, InvoiceListPage } from '@/pages/invoices';
import { SupplierDirectoryPage, SupplierPoDetailPage, SupplierProfilePage } from '@/pages/suppliers';
import { InventoryPage } from '@/pages/inventory';
import { ItemListPage } from '@/pages/inventory/items/item-list-page';
import { ItemCreatePage } from '@/pages/inventory/items/item-create-page';
import { ItemDetailPage } from '@/pages/inventory/items/item-detail-page';
import { MovementListPage } from '@/pages/inventory/movements/movement-list-page';
import { MovementCreatePage } from '@/pages/inventory/movements/movement-create-page';
import { MovementDetailPage } from '@/pages/inventory/movements/movement-detail-page';
import { LowStockAlertPage } from '@/pages/inventory/alerts/low-stock-alert-page';
import { AssetsPage } from '@/pages/assets';
import { OrdersPage } from '@/pages/orders';
import { RiskPage } from '@/pages/risk';
import { AnalyticsPage } from '@/pages/analytics';
import {
    SettingsPage,
    CompanySettingsPage,
    LocalizationSettingsPage,
    NumberingSettingsPage,
} from '@/pages/settings';
import {
    AdminApiKeysPage,
    AdminAuditLogPage,
    AdminCompanyApprovalsPage,
    AdminHomePage,
    AdminPlansPage,
    AdminRateLimitsPage,
    AdminRolesPage,
    AdminWebhooksPage,
} from '@/pages/admin';
import { ReceivingListPage } from '@/pages/receiving/receiving-list-page';
import { ReceivingCreatePage } from '@/pages/receiving/receiving-create-page';
import { ReceivingDetailPage } from '@/pages/receiving/receiving-detail-page';
import { MatchWorkbenchPage } from '@/pages/matching/match-workbench-page';
import { CreditNoteListPage } from '@/pages/credits/credit-note-list-page';
import { CreditNoteDetailPage } from '@/pages/credits/credit-note-detail-page';
import { LoginPage, ForgotPasswordPage, ResetPasswordPage, VerifyEmailPage, RegisterPage } from '@/pages/auth';
import { PlanSelectionPage } from '@/pages/onboarding';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { NotFoundPage } from '@/pages/errors/not-found-page';
import { type ReactElement } from 'react';

export function AppRoutes(): ReactElement {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password/:token" element={<ResetPasswordPage />} />
            <Route path="/verify-email" element={<VerifyEmailPage />} />
            <Route element={<RequireAuth />}>
                <Route path="/app/setup/plan" element={<PlanSelectionPage />} />
                <Route element={<RequireActivePlan />}>
                    <Route path="/app" element={<AppLayout />}>
                        <Route index element={<DashboardPage />} />
                        <Route path="rfqs" element={<RfqListPage />} />
                        <Route path="rfqs/new" element={<RfqCreateWizard />} />
                        <Route path="rfqs/:id" element={<RfqDetailPage />} />
                        <Route path="rfqs/:rfqId/awards" element={<AwardReviewPage />} />
                        <Route path="rfqs/:rfqId/quotes" element={<QuoteListPage />} />
                        <Route path="quotes" element={<QuoteListPage />} />
                        <Route path="quotes/:quoteId" element={<QuoteDetailPage />} />
                        <Route path="suppliers" element={<SupplierDirectoryPage />} />
                        <Route path="suppliers/:supplierId" element={<SupplierProfilePage />} />
                        <Route element={<RequireSupplierAccess />}>
                            <Route path="suppliers/rfqs/:rfqId/quotes/new" element={<SupplierQuoteCreatePage />} />
                            <Route path="suppliers/quotes/:quoteId" element={<SupplierQuoteEditPage />} />
                            <Route path="suppliers/pos/:purchaseOrderId" element={<SupplierPoDetailPage />} />
                        </Route>
                        <Route path="purchase-orders" element={<PoListPage />} />
                        <Route path="purchase-orders/:purchaseOrderId" element={<PoDetailPage />} />
                        <Route path="pos" element={<PoListPage />} />
                        <Route path="pos/:purchaseOrderId" element={<PoDetailPage />} />
                        <Route path="receiving" element={<ReceivingListPage />} />
                        <Route path="receiving/new" element={<ReceivingCreatePage />} />
                        <Route path="receiving/:grnId" element={<ReceivingDetailPage />} />
                        <Route path="matching" element={<MatchWorkbenchPage />} />
                        <Route path="credit-notes" element={<CreditNoteListPage />} />
                        <Route path="credit-notes/:creditId" element={<CreditNoteDetailPage />} />
                        <Route path="invoices" element={<InvoiceListPage />} />
                        <Route path="invoices/:invoiceId" element={<InvoiceDetailPage />} />
                        <Route path="inventory" element={<InventoryPage />} />
                        <Route path="inventory/items" element={<ItemListPage />} />
                        <Route path="inventory/items/new" element={<ItemCreatePage />} />
                        <Route path="inventory/items/:itemId" element={<ItemDetailPage />} />
                        <Route path="inventory/movements" element={<MovementListPage />} />
                        <Route path="inventory/movements/new" element={<MovementCreatePage />} />
                        <Route path="inventory/movements/:movementId" element={<MovementDetailPage />} />
                        <Route path="inventory/alerts" element={<LowStockAlertPage />} />
                        <Route path="assets" element={<AssetsPage />} />
                        <Route path="orders" element={<OrdersPage />} />
                        <Route path="risk" element={<RiskPage />} />
                        <Route path="analytics" element={<AnalyticsPage />} />
                        <Route path="settings" element={<SettingsPage />} />
                        <Route path="settings/company" element={<CompanySettingsPage />} />
                        <Route path="settings/localization" element={<LocalizationSettingsPage />} />
                        <Route path="settings/numbering" element={<NumberingSettingsPage />} />
                    <Route element={<RequireAdminConsole />}>
                        <Route path="admin" element={<AdminHomePage />} />
                        <Route path="admin/plans" element={<AdminPlansPage />} />
                        <Route path="admin/roles" element={<AdminRolesPage />} />
                        <Route path="admin/api-keys" element={<AdminApiKeysPage />} />
                        <Route path="admin/webhooks" element={<AdminWebhooksPage />} />
                        <Route path="admin/rate-limits" element={<AdminRateLimitsPage />} />
                        <Route path="admin/audit" element={<AdminAuditLogPage />} />
                        <Route path="admin/company-approvals" element={<AdminCompanyApprovalsPage />} />
                    </Route>
                        <Route path="access-denied" element={<AccessDeniedPage />} />
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
