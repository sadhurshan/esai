import { Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth } from '@/components/require-auth';
import { RequireSupplierAccess } from '@/components/require-supplier-access';
import { RequireDigitalTwinAccess } from '@/components/require-digital-twin-access';
import { RequireActivePlan } from '@/components/require-active-plan';
import { RequireAdminConsole } from '@/components/require-admin-console';
import { AppLayout } from '@/layouts/app-layout';
import { DashboardPage, SupplierDashboardPage } from '@/pages/dashboard';
import { RfqCreateWizard, RfqDetailPage, RfqListPage } from '@/pages/rfqs';
import { RfpProposalReviewPage } from '@/pages/rfps';
import { AwardReviewPage } from '@/pages/awards/award-review-page';
import {
    QuoteDetailPage,
    QuoteListPage,
    SupplierQuoteCreatePage,
    SupplierQuoteEditPage,
    SupplierQuoteListPage,
} from '@/pages/quotes';
import { PoDetailPage, PoListPage } from '@/pages/pos';
import { InvoiceDetailPage, InvoiceListPage } from '@/pages/invoices';
import {
    SupplierCompanyProfilePage,
    SupplierDirectoryPage,
    SupplierInvoiceCreatePage,
    SupplierInvoiceEditPage,
    SupplierInvoiceDetailPage,
    SupplierInvoiceListPage,
    SupplierPoDetailPage,
    SupplierProfilePage,
    SupplierRfpProposalPage,
} from '@/pages/suppliers';
import { InventoryPage } from '@/pages/inventory';
import { ItemListPage } from '@/pages/inventory/items/item-list-page';
import { ItemCreatePage } from '@/pages/inventory/items/item-create-page';
import { ItemDetailPage } from '@/pages/inventory/items/item-detail-page';
import { MovementListPage } from '@/pages/inventory/movements/movement-list-page';
import { MovementCreatePage } from '@/pages/inventory/movements/movement-create-page';
import { MovementDetailPage } from '@/pages/inventory/movements/movement-detail-page';
import { LowStockAlertPage } from '@/pages/inventory/alerts/low-stock-alert-page';
import { AssetsPage } from '@/pages/assets';
import {
    BuyerOrderDetailPage,
    BuyerOrderListPage,
    SupplierOrderDetailPage,
    SupplierOrderListPage,
} from '@/pages/orders';
import { RiskPage } from '@/pages/risk';
import { AnalyticsPage, ForecastReportPage, SupplierPerformancePage } from '@/pages/analytics';
import {
    SettingsPage,
    CompanySettingsPage,
    LocalizationSettingsPage,
    NumberingSettingsPage,
    ProfileSettingsPage,
    NotificationSettingsPage,
    CompanyInvitationsPage,
    CompanyMembersPage,
    CompanyRolesPage,
    BillingSettingsPage,
} from '@/pages/settings';
import {
    AdminApiKeysPage,
    AdminAiActivityLogPage,
    AdminAiModelHealthPage,
    AdminAiTrainingPage,
    AdminAuditLogPage,
    AdminCompanyApprovalsPage,
    AdminDigitalTwinCategoriesPage,
    AdminDigitalTwinCreatePage,
    AdminDigitalTwinDetailPage,
    AdminDigitalTwinListPage,
    AdminHomePage,
    AdminPlansPage,
    AdminRateLimitsPage,
    AdminRolesPage,
    AdminSupplierScrapePage,
    AdminSupplierApplicationsPage,
    AdminWebhooksPage,
} from '@/pages/admin';
import { NotificationCenterPage } from '@/pages/notifications/notification-center-page';
import { EventDeliveriesPage } from '@/pages/events/event-deliveries-page';
import { ReceivingListPage } from '@/pages/receiving/receiving-list-page';
import { ReceivingCreatePage } from '@/pages/receiving/receiving-create-page';
import { ReceivingDetailPage } from '@/pages/receiving/receiving-detail-page';
import { DownloadCenterPage } from '@/pages/downloads';
import { MatchWorkbenchPage } from '@/pages/matching/match-workbench-page';
import { CreditNoteListPage } from '@/pages/credits/credit-note-list-page';
import { CreditNoteDetailPage } from '@/pages/credits/credit-note-detail-page';
import {
    LoginPage,
    AcceptCompanyInvitationPage,
    ForgotPasswordPage,
    ResetPasswordPage,
    VerifyEmailPage,
    RegisterPage,
} from '@/pages/auth';
import { PlanSelectionPage } from '@/pages/onboarding';
import { DigitalTwinLibraryPage, DigitalTwinDetailPage } from '@/pages/digital-twins';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { NotFoundPage } from '@/pages/errors/not-found-page';
import { type ReactElement } from 'react';
import { FormattingProvider } from '@/contexts/formatting-context';
import { useAuth } from '@/contexts/auth-context';
import { isPlatformRole } from '@/constants/platform-roles';

export function AppRoutes(): ReactElement {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password/:token" element={<ResetPasswordPage />} />
            <Route path="/verify-email" element={<VerifyEmailPage />} />
            <Route element={<RequireAuth />}>
                <Route path="/invitations/accept/:token" element={<AcceptCompanyInvitationPage />} />
                <Route
                    path="/app/setup/plan"
                    element={(
                        <FormattingProvider disableRemoteFetch>
                            <PlanSelectionPage />
                        </FormattingProvider>
                    )}
                />
                <Route element={<RequireActivePlan />}>
                    <Route path="/app" element={<AppLayout />}>
                        <Route index element={<AppIndexRoute />} />
                        <Route path="notifications" element={<NotificationCenterPage />} />
                        <Route path="downloads" element={<DownloadCenterPage />} />
                        <Route path="events/deliveries" element={<EventDeliveriesPage />} />
                        <Route path="rfqs" element={<RfqListPage />} />
                        <Route path="rfqs/new" element={<RfqCreateWizard />} />
                        <Route path="rfqs/:id" element={<RfqDetailPage />} />
                        <Route path="rfqs/:rfqId/awards" element={<AwardReviewPage />} />
                        <Route path="rfqs/:rfqId/quotes" element={<QuoteListPage />} />
                        <Route path="rfps/:rfpId/proposals" element={<RfpProposalReviewPage />} />
                        <Route path="quotes" element={<QuoteListPage />} />
                        <Route path="quotes/:quoteId" element={<QuoteDetailPage />} />
                        <Route path="suppliers" element={<SupplierDirectoryPage />} />
                        <Route path="suppliers/:supplierId" element={<SupplierProfilePage />} />
                        <Route element={<RequireSupplierAccess />}>
                            <Route path="supplier" element={<SupplierDashboardPage />} />
                            <Route path="supplier/rfqs" element={<RfqListPage />} />
                            <Route path="supplier/quotes" element={<SupplierQuoteListPage />} />
                            <Route path="supplier/company-profile" element={<SupplierCompanyProfilePage />} />
                            <Route path="suppliers/rfps/:rfpId/proposals/new" element={<SupplierRfpProposalPage />} />
                            <Route path="supplier/rfqs/:rfqId/quotes/new" element={<SupplierQuoteCreatePage />} />
                            <Route path="supplier/quotes/:quoteId" element={<SupplierQuoteEditPage />} />
                            <Route path="supplier/invoices" element={<SupplierInvoiceListPage />} />
                            <Route path="supplier/invoices/create" element={<SupplierInvoiceCreatePage />} />
                            <Route path="supplier/invoices/:invoiceId/edit" element={<SupplierInvoiceEditPage />} />
                            <Route path="supplier/invoices/:invoiceId" element={<SupplierInvoiceDetailPage />} />
                            <Route path="suppliers/pos/:purchaseOrderId" element={<SupplierPoDetailPage />} />
                            <Route path="supplier/orders" element={<SupplierOrderListPage />} />
                            <Route path="supplier/orders/:soId" element={<SupplierOrderDetailPage />} />
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
                        <Route path="inventory/alerts" element={<Navigate to="/app/inventory/alerts/low-stock" replace />} />
                        <Route path="inventory/alerts/low-stock" element={<LowStockAlertPage />} />
                        <Route element={<RequireDigitalTwinAccess />}>
                            <Route path="library/digital-twins" element={<DigitalTwinLibraryPage />} />
                            <Route path="library/digital-twins/:id" element={<DigitalTwinDetailPage />} />
                        </Route>
                        <Route path="assets" element={<AssetsPage />} />
                        <Route path="orders" element={<BuyerOrderListPage />} />
                        <Route path="orders/:soId" element={<BuyerOrderDetailPage />} />
                        <Route path="risk" element={<RiskPage />} />
                        <Route path="analytics" element={<AnalyticsPage />} />
                        <Route path="analytics/forecast" element={<ForecastReportPage />} />
                        <Route path="analytics/supplier-performance" element={<SupplierPerformancePage />} />
                        <Route path="settings" element={<SettingsPage />} />
                        <Route path="settings/profile" element={<ProfileSettingsPage />} />
                        <Route path="settings/notifications" element={<NotificationSettingsPage />} />
                        <Route path="settings/team" element={<CompanyMembersPage />} />
                        <Route path="settings/invitations" element={<CompanyInvitationsPage />} />
                        <Route path="settings/roles" element={<CompanyRolesPage />} />
                        <Route path="settings/company" element={<CompanySettingsPage />} />
                        <Route path="settings/billing" element={<BillingSettingsPage />} />
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
                            <Route path="admin/ai-model-health" element={<AdminAiModelHealthPage />} />
                            <Route path="admin/ai-training" element={<AdminAiTrainingPage />} />
                            <Route path="admin/ai-events" element={<AdminAiActivityLogPage />} />
                            <Route path="admin/company-approvals" element={<AdminCompanyApprovalsPage />} />
                            <Route path="admin/supplier-applications" element={<AdminSupplierApplicationsPage />} />
                            <Route path="admin/supplier-scrapes" element={<AdminSupplierScrapePage />} />
                            <Route path="admin/digital-twins" element={<AdminDigitalTwinListPage />} />
                            <Route path="admin/digital-twins/categories" element={<AdminDigitalTwinCategoriesPage />} />
                            <Route path="admin/digital-twins/new" element={<AdminDigitalTwinCreatePage />} />
                            <Route path="admin/digital-twins/:id" element={<AdminDigitalTwinDetailPage />} />
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

function AppIndexRoute(): ReactElement {
    const { state } = useAuth();
    const role = state.user?.role ?? null;

    if (isPlatformRole(role)) {
        return <Navigate to="/app/admin" replace />;
    }

    return <DashboardPage />;
}
