import { expect, type Page, test } from '@playwright/test';

const buyerEmail = process.env.PLAYWRIGHT_BUYER_EMAIL ?? '';
const buyerPassword = process.env.PLAYWRIGHT_BUYER_PASSWORD ?? '';
const supplierEmail = process.env.PLAYWRIGHT_SUPPLIER_EMAIL ?? '';
const supplierPassword = process.env.PLAYWRIGHT_SUPPLIER_PASSWORD ?? '';
const adminEmail = process.env.PLAYWRIGHT_ADMIN_EMAIL ?? '';
const adminPassword = process.env.PLAYWRIGHT_ADMIN_PASSWORD ?? '';

const buyerCredsMissing = buyerEmail === '' || buyerPassword === '';
const supplierCredsMissing = supplierEmail === '' || supplierPassword === '';
const adminCredsMissing = adminEmail === '' || adminPassword === '';

test.describe('UAT smoke: buyer', () => {
  test.skip(buyerCredsMissing, 'Set PLAYWRIGHT_BUYER_EMAIL and PLAYWRIGHT_BUYER_PASSWORD to run buyer UAT smoke tests.');

  test('buyer can reach core procurement hubs', async ({ page }) => {
    await login(page, buyerEmail, buyerPassword);

    await page.goto('/app');
    await expect(page.getByRole('heading', { name: /operations dashboard/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /create rfq/i })).toBeVisible();
    await expect(page.getByText(/open rfqs/i)).toBeVisible();

    await page.goto('/app/suppliers');
    await expect(page.getByRole('heading', { name: /supplier directory/i })).toBeVisible();
    await expect(page.getByText('Search', { exact: true })).toBeVisible();
    await expect(page.getByText('Capability', { exact: true })).toBeVisible();
    await expect(page.getByText('Material', { exact: true })).toBeVisible();
    await expect(page.getByText('Industry', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /clear filters/i })).toBeVisible();

    await page.goto('/app/rfqs');
    await expect(page.getByRole('heading', { name: 'Requests for Quotation' })).toBeVisible();
    await expect(page.getByPlaceholder('Search RFQs')).toBeVisible();
    await expect(page.getByRole('button', { name: /new rfq/i })).toBeVisible();

    await page.goto('/app/quotes');
    await expect(
      page.getByRole('heading', { name: /supplier quotes|select an rfq/i }),
    ).toBeVisible();
    await expect(page.getByText('Bidding', { exact: true })).toBeVisible();

    await page.goto('/app/purchase-orders');
    await expect(page.getByRole('heading', { name: 'Purchase orders', level: 1 })).toBeVisible();
    await expect(page.getByRole('button', { name: /create from rfq/i })).toBeVisible();
    await expect(page.getByText('Status', { exact: true })).toBeVisible();
    await expect(page.getByText('Acknowledgement', { exact: true })).toBeVisible();

    await page.goto('/app/orders');
    await expect(page.getByRole('heading', { name: /orders/i })).toBeVisible();
    await expect(page.getByText('Supplier', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /reset filters/i })).toBeVisible();

    await page.goto('/app/invoices');
    await expect(page.getByRole('heading', { name: 'Invoices', level: 1 })).toBeVisible();
    await expect(page.getByRole('button', { name: /go to purchase orders/i })).toBeVisible();
    await expect(page.getByText('Status', { exact: true })).toBeVisible();
    await expect(page.getByText('Supplier', { exact: true })).toBeVisible();
  });
});

test.describe('UAT smoke: supplier', () => {
  test.skip(supplierCredsMissing, 'Set PLAYWRIGHT_SUPPLIER_EMAIL and PLAYWRIGHT_SUPPLIER_PASSWORD to run supplier UAT smoke tests.');

  test('supplier can reach open rfqs and invoices', async ({ page }) => {
    await login(page, supplierEmail, supplierPassword);

    await page.goto('/app');
    await expect(page.getByRole('heading', { name: /supplier workspace/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /manage quotes/i })).toBeVisible();
    await expect(page.getByText(/new rfq invites/i)).toBeVisible();

    await page.goto('/app/rfqs');
    const supplierRfqHeading = page.locator('h1', {
      hasText: /rfqs received|requests for quotation|supplier approval in progress|approval required/i,
    });
    await expect(supplierRfqHeading.first()).toBeVisible();
    await expect(page.getByPlaceholder('Search RFQs')).toBeVisible();

    await page.goto('/app/purchase-orders/supplier');
    await expect(page.getByRole('heading', { name: /purchase orders/i })).toBeVisible();

    await page.goto('/app/supplier/orders');
    await expect(page.getByRole('heading', { name: /sales orders/i })).toBeVisible();
    await expect(page.getByText('Buyer company ID', { exact: true })).toBeVisible();

    await page.goto('/app/supplier/invoices');
    await expect(page.getByRole('heading', { name: /invoices/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /create invoice/i })).toBeVisible();
    await expect(page.getByText('Status', { exact: true })).toBeVisible();
  });
});

test.describe('UAT smoke: super admin', () => {
  test.skip(adminCredsMissing, 'Set PLAYWRIGHT_ADMIN_EMAIL and PLAYWRIGHT_ADMIN_PASSWORD to run admin UAT smoke tests.');

  test('admin can reach supplier applications and audit log', async ({ page }) => {
    await login(page, adminEmail, adminPassword);

    await page.goto('/app/admin/companies');
    await expect(page.getByRole('heading', { name: /company approvals|companies/i })).toBeVisible();
    await expect(page.getByText('Queues', { exact: true })).toBeVisible();

    await page.goto('/app/admin/supplier-applications');
    await expect(page.getByRole('heading', { name: 'Supplier applications' })).toBeVisible();
    await expect(page.getByText('Queues', { exact: true })).toBeVisible();

    await page.goto('/app/admin/audit');
    await expect(page.getByRole('heading', { name: 'Audit log' })).toBeVisible();
    await expect(page.getByText('Filters', { exact: true })).toBeVisible();
  });
});

async function login(page: Page, email: string, password: string) {
  await page.goto('/login');

  const loginForm = page.locator('form');
  await expect(loginForm).toBeVisible();

  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);

  await Promise.all([
    page.waitForURL(/\/app(\b|\/).*/i, { timeout: 60000 }),
    page.getByRole('button', { name: /sign in/i }).click(),
  ]);
}
