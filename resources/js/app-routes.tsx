import { RequireActivePlan } from '@/components/require-active-plan';
import { RequireAdminConsole } from '@/components/require-admin-console';
import { RequireAuth } from '@/components/require-auth';
import { RequireDigitalTwinAccess } from '@/components/require-digital-twin-access';
import { RequireSupplierAccess } from '@/components/require-supplier-access';
import { isPlatformRole } from '@/constants/platform-roles';
import { useAuth } from '@/contexts/auth-context';
import { FormattingProvider } from '@/contexts/formatting-context';
import { AppLayout } from '@/layouts/app-layout';
import {
    AdminAiActivityLogPage,
    AdminAiModelHealthPage,
    AdminAiTrainingPage,
    AdminAiUsageDashboardPage,
    AdminApiKeysPage,
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
    AdminSupplierApplicationsPage,
    AdminSupplierScrapePage,
    AdminWebhooksPage,
} from '@/pages/admin';
import {
    AnalyticsPage,
    ForecastReportPage,
    SupplierPerformancePage,
} from '@/pages/analytics';
import { AssetsPage } from '@/pages/assets';
import {
    AcceptCompanyInvitationPage,
    ForgotPasswordPage,
    LoginPage,
    RegisterPage,
    ResetPasswordPage,
    VerifyEmailPage,
} from '@/pages/auth';
import { AwardReviewPage } from '@/pages/awards/award-review-page';
import { CreditNoteDetailPage } from '@/pages/credits/credit-note-detail-page';
import { CreditNoteListPage } from '@/pages/credits/credit-note-list-page';
import { DashboardPage, SupplierDashboardPage } from '@/pages/dashboard';
import {
    DigitalTwinDetailPage,
    DigitalTwinLibraryPage,
} from '@/pages/digital-twins';
import { DownloadCenterPage } from '@/pages/downloads';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { NotFoundPage } from '@/pages/errors/not-found-page';
import { EventDeliveriesPage } from '@/pages/events/event-deliveries-page';
import { InventoryPage } from '@/pages/inventory';
import { LowStockAlertPage } from '@/pages/inventory/alerts/low-stock-alert-page';
import { ItemCreatePage } from '@/pages/inventory/items/item-create-page';
import { ItemDetailPage } from '@/pages/inventory/items/item-detail-page';
import { ItemListPage } from '@/pages/inventory/items/item-list-page';
import { MovementCreatePage } from '@/pages/inventory/movements/movement-create-page';
import { MovementDetailPage } from '@/pages/inventory/movements/movement-detail-page';
import { MovementListPage } from '@/pages/inventory/movements/movement-list-page';
import { InvoiceDetailPage, InvoiceListPage } from '@/pages/invoices';
import { LandingPage } from '@/pages/landing/landing-page';
import { MatchWorkbenchPage } from '@/pages/matching/match-workbench-page';
import { NotificationCenterPage } from '@/pages/notifications/notification-center-page';
import { PlanSelectionPage } from '@/pages/onboarding';
import {
    BuyerOrderDetailPage,
    BuyerOrderListPage,
    SupplierOrderDetailPage,
    SupplierOrderListPage,
    SupplierPurchaseOrdersPage,
} from '@/pages/orders';
import { PoDetailPage, PoListPage } from '@/pages/pos';
import {
    QuoteDetailPage,
    QuoteListPage,
    SupplierQuoteCreatePage,
    SupplierQuoteEditPage,
    SupplierQuoteListPage,
} from '@/pages/quotes';
import { ReceivingCreatePage } from '@/pages/receiving/receiving-create-page';
import { ReceivingDetailPage } from '@/pages/receiving/receiving-detail-page';
import { ReceivingListPage } from '@/pages/receiving/receiving-list-page';
import { RfpProposalReviewPage } from '@/pages/rfps';
import { RfqCreateWizard, RfqDetailPage, RfqListPage } from '@/pages/rfqs';
import { RiskPage } from '@/pages/risk';
import {
    BillingSettingsPage,
    CompanyInvitationsPage,
    CompanyMembersPage,
    CompanyRolesPage,
    CompanySettingsPage,
    LocalizationSettingsPage,
    NotificationSettingsPage,
    NumberingSettingsPage,
    ProfileSettingsPage,
    SettingsPage,
} from '@/pages/settings';
import { SupplierWaitingPage } from '@/pages/setup/supplier-waiting-page';
import {
    SupplierCompanyProfilePage,
    SupplierDirectoryPage,
    SupplierInvoiceCreatePage,
    SupplierInvoiceDetailPage,
    SupplierInvoiceEditPage,
    SupplierInvoiceListPage,
    SupplierPoDetailPage,
    SupplierProfilePage,
    SupplierRfpProposalPage,
} from '@/pages/suppliers';
import { type ReactElement } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

export function AppRoutes(): ReactElement {
    return (
        <Routes>
            <Route path="/" element={<LandingPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route
                path="/reset-password/:token"
                element={<ResetPasswordPage />}
            />
            <Route path="/verify-email" element={<VerifyEmailPage />} />
            <Route element={<RequireAuth />}>
                <Route
                    path="/invitations/accept/:token"
                    element={<AcceptCompanyInvitationPage />}
                />
                <Route
                    path="/app/setup/plan"
                    element={
                        <FormattingProvider disableRemoteFetch>
                            <PlanSelectionPage />
                        </FormattingProvider>
                    }
                />
                <Route
                    path="/app/setup/supplier-waiting"
                    element={<AppLayout />}
                >
                    <Route index element={<SupplierWaitingPage />} />
                </Route>
                <Route element={<RequireActivePlan />}>
                    <Route path="/app" element={<AppLayout />}>
                        <Route index element={<AppIndexRoute />} />
                        <Route
                            path="notifications"
                            element={<NotificationCenterPage />}
                        />
                        <Route
                            path="downloads"
                            element={<DownloadCenterPage />}
                        />
                        <Route
                            path="events/deliveries"
                            element={<EventDeliveriesPage />}
                        />
                        <Route path="rfqs" element={<RfqListPage />} />
                        <Route path="rfqs/new" element={<RfqCreateWizard />} />
                        <Route path="rfqs/:id" element={<RfqDetailPage />} />
                        <Route
                            path="rfqs/:rfqId/awards"
                            element={<AwardReviewPage />}
                        />
                        <Route
                            path="rfqs/:rfqId/quotes"
                            element={<QuoteListPage />}
                        />
                        <Route
                            path="rfps/:rfpId/proposals"
                            element={<RfpProposalReviewPage />}
                        />
                        <Route path="quotes" element={<QuoteListPage />} />
                        <Route
                            path="quotes/:quoteId"
                            element={<QuoteDetailPage />}
                        />
                        <Route
                            path="suppliers"
                            element={<SupplierDirectoryPage />}
                        />
                        <Route
                            path="suppliers/:supplierId"
                            element={<SupplierProfilePage />}
                        />
                        <Route element={<RequireSupplierAccess />}>
                            <Route
                                path="supplier"
                                element={<SupplierDashboardPage />}
                            />
                            <Route
                                path="supplier/rfqs"
                                element={<RfqListPage />}
                            />
                            <Route
                                path="supplier/quotes"
                                element={<SupplierQuoteListPage />}
                            />
                            <Route
                                path="supplier/company-profile"
                                element={<SupplierCompanyProfilePage />}
                            />
                            <Route
                                path="suppliers/rfps/:rfpId/proposals/new"
                                element={<SupplierRfpProposalPage />}
                            />
                            <Route
                                path="supplier/rfqs/:rfqId/quotes/new"
                                element={<SupplierQuoteCreatePage />}
                            />
                            <Route
                                path="supplier/quotes/:quoteId"
                                element={<SupplierQuoteEditPage />}
                            />
                            <Route
                                path="supplier/invoices"
                                element={<SupplierInvoiceListPage />}
                            />
                            <Route
                                path="supplier/invoices/create"
                                element={<SupplierInvoiceCreatePage />}
                            />
                            <Route
                                path="supplier/invoices/:invoiceId/edit"
                                element={<SupplierInvoiceEditPage />}
                            />
                            <Route
                                path="supplier/invoices/:invoiceId"
                                element={<SupplierInvoiceDetailPage />}
                            />
                            <Route
                                path="suppliers/pos/:purchaseOrderId"
                                element={<SupplierPoDetailPage />}
                            />
                            <Route
                                path="purchase-orders/supplier"
                                element={<SupplierPurchaseOrdersPage />}
                            />
                            <Route
                                path="supplier/orders"
                                element={<SupplierOrderListPage />}
                            />
                            <Route
                                path="supplier/orders/:soId"
                                element={<SupplierOrderDetailPage />}
                            />
                        </Route>
                        <Route
                            path="purchase-orders"
                            element={<PoListPage />}
                        />
                        <Route
                            path="purchase-orders/:purchaseOrderId"
                            element={<PoDetailPage />}
                        />
                        <Route path="pos" element={<PoListPage />} />
                        <Route
                            path="pos/:purchaseOrderId"
                            element={<PoDetailPage />}
                        />
                        <Route
                            path="receiving"
                            element={<ReceivingListPage />}
                        />
                        <Route
                            path="receiving/new"
                            element={<ReceivingCreatePage />}
                        />
                        <Route
                            path="receiving/:grnId"
                            element={<ReceivingDetailPage />}
                        />
                        <Route
                            path="matching"
                            element={<MatchWorkbenchPage />}
                        />
                        <Route
                            path="credit-notes"
                            element={<CreditNoteListPage />}
                        />
                        <Route
                            path="credit-notes/:creditId"
                            element={<CreditNoteDetailPage />}
                        />
                        <Route path="invoices" element={<InvoiceListPage />} />
                        <Route
                            path="invoices/:invoiceId"
                            element={<InvoiceDetailPage />}
                        />
                        <Route path="inventory" element={<InventoryPage />} />
                        <Route
                            path="inventory/items"
                            element={<ItemListPage />}
                        />
                        <Route
                            path="inventory/items/new"
                            element={<ItemCreatePage />}
                        />
                        <Route
                            path="inventory/items/:itemId"
                            element={<ItemDetailPage />}
                        />
                        <Route
                            path="inventory/movements"
                            element={<MovementListPage />}
                        />
                        <Route
                            path="inventory/movements/new"
                            element={<MovementCreatePage />}
                        />
                        <Route
                            path="inventory/movements/:movementId"
                            element={<MovementDetailPage />}
                        />
                        <Route
                            path="inventory/alerts"
                            element={
                                <Navigate
                                    to="/app/inventory/alerts/low-stock"
                                    replace
                                />
                            }
                        />
                        <Route
                            path="inventory/alerts/low-stock"
                            element={<LowStockAlertPage />}
                        />
                        <Route element={<RequireDigitalTwinAccess />}>
                            <Route
                                path="library/digital-twins"
                                element={<DigitalTwinLibraryPage />}
                            />
                            <Route
                                path="library/digital-twins/:id"
                                element={<DigitalTwinDetailPage />}
                            />
                        </Route>
                        <Route path="assets" element={<AssetsPage />} />
                        <Route path="orders" element={<BuyerOrderListPage />} />
                        <Route
                            path="orders/:soId"
                            element={<BuyerOrderDetailPage />}
                        />
                        <Route path="risk" element={<RiskPage />} />
                        <Route path="analytics" element={<AnalyticsPage />} />
                        <Route
                            path="analytics/forecast"
                            element={<ForecastReportPage />}
                        />
                        <Route
                            path="analytics/supplier-performance"
                            element={<SupplierPerformancePage />}
                        />
                        <Route path="settings" element={<SettingsPage />} />
                        <Route
                            path="settings/profile"
                            element={<ProfileSettingsPage />}
                        />
                        <Route
                            path="settings/notifications"
                            element={<NotificationSettingsPage />}
                        />
                        <Route
                            path="settings/team"
                            element={<CompanyMembersPage />}
                        />
                        <Route
                            path="settings/invitations"
                            element={<CompanyInvitationsPage />}
                        />
                        <Route
                            path="settings/roles"
                            element={<CompanyRolesPage />}
                        />
                        <Route
                            path="settings/company"
                            element={<CompanySettingsPage />}
                        />
                        <Route
                            path="settings/billing"
                            element={<BillingSettingsPage />}
                        />
                        <Route
                            path="settings/localization"
                            element={<LocalizationSettingsPage />}
                        />
                        <Route
                            path="settings/numbering"
                            element={<NumberingSettingsPage />}
                        />
                        <Route element={<RequireAdminConsole />}>
                            <Route path="admin" element={<AdminHomePage />} />
                            <Route
                                path="admin/plans"
                                element={<AdminPlansPage />}
                            />
                            <Route
                                path="admin/roles"
                                element={<AdminRolesPage />}
                            />
                            <Route
                                path="admin/api-keys"
                                element={<AdminApiKeysPage />}
                            />
                            <Route
                                path="admin/webhooks"
                                element={<AdminWebhooksPage />}
                            />
                            <Route
                                path="admin/rate-limits"
                                element={<AdminRateLimitsPage />}
                            />
                            <Route
                                path="admin/audit"
                                element={<AdminAuditLogPage />}
                            />
                            <Route
                                path="admin/ai-model-health"
                                element={<AdminAiModelHealthPage />}
                            />
                            <Route
                                path="admin/ai-usage"
                                element={<AdminAiUsageDashboardPage />}
                            />
                            <Route
                                path="admin/ai-training"
                                element={<AdminAiTrainingPage />}
                            />
                            <Route
                                path="admin/ai-events"
                                element={<AdminAiActivityLogPage />}
                            />
                            <Route
                                path="admin/companies"
                                element={
                                    <Navigate
                                        to="/app/admin/company-approvals"
                                        replace
                                    />
                                }
                            />
                            <Route
                                path="admin/company-approvals"
                                element={<AdminCompanyApprovalsPage />}
                            />
                            <Route
                                path="admin/supplier-applications"
                                element={<AdminSupplierApplicationsPage />}
                            />
                            <Route
                                path="admin/supplier-scrapes"
                                element={<AdminSupplierScrapePage />}
                            />
                            <Route
                                path="admin/digital-twins"
                                element={<AdminDigitalTwinListPage />}
                            />
                            <Route
                                path="admin/digital-twins/categories"
                                element={<AdminDigitalTwinCategoriesPage />}
                            />
                            <Route
                                path="admin/digital-twins/new"
                                element={<AdminDigitalTwinCreatePage />}
                            />
                            <Route
                                path="admin/digital-twins/:id"
                                element={<AdminDigitalTwinDetailPage />}
                            />
                        </Route>
                        <Route
                            path="access-denied"
                            element={<AccessDeniedPage />}
                        />
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}

function AppIndexRoute(): ReactElement {
    const { state, activePersona } = useAuth();
    const role = state.user?.role ?? null;
    const personas = state.personas ?? [];
    const hasSupplierPersona = personas.some(
        (persona) => persona.type === 'supplier',
    );
    const hasBuyerPersona = personas.some(
        (persona) => persona.type === 'buyer',
    );
    const isSupplierRole =
        typeof role === 'string' && role.startsWith('supplier_');
    const isSupplierStart = state.company?.start_mode === 'supplier';

    if (isPlatformRole(role)) {
        return <Navigate to="/app/admin" replace />;
    }

    if (
        activePersona?.type === 'supplier' ||
        (!hasBuyerPersona && hasSupplierPersona) ||
        isSupplierRole ||
        (hasSupplierPersona && isSupplierStart)
    ) {
        return <Navigate to="/app/supplier" replace />;
    }

    return <DashboardPage />;
}
