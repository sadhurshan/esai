import { expect, type Page, test } from '@playwright/test';

const supplierEmail = process.env.PLAYWRIGHT_SUPPLIER_EMAIL ?? '';
const supplierPassword = process.env.PLAYWRIGHT_SUPPLIER_PASSWORD ?? '';
const buyerEmail = process.env.PLAYWRIGHT_BUYER_EMAIL ?? '';
const buyerPassword = process.env.PLAYWRIGHT_BUYER_PASSWORD ?? '';

const supplierCredsMissing = supplierEmail === '' || supplierPassword === '';
const buyerCredsMissing = buyerEmail === '' || buyerPassword === '';

test.describe('Supplier invoicing portal', () => {
    test.skip(supplierCredsMissing, 'Set PLAYWRIGHT_SUPPLIER_EMAIL and PLAYWRIGHT_SUPPLIER_PASSWORD to run supplier portal smoke tests.');

    test('supplier can reach the invoice dashboard', async ({ page }) => {
        await login(page, supplierEmail, supplierPassword);

        await page.goto('/app/supplier/invoices');

        await expect(page.getByRole('heading', { name: 'Invoices' })).toBeVisible();
        await expect(page.getByText('Submit invoices against purchase orders', { exact: false })).toBeVisible();
        await expect(page.getByLabel('Status')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Reset' })).toBeVisible();
    });
});

test.describe('Buyer invoice review workspace', () => {
    test.skip(buyerCredsMissing, 'Set PLAYWRIGHT_BUYER_EMAIL and PLAYWRIGHT_BUYER_PASSWORD to run buyer invoice smoke tests.');

    test('buyer can open the invoice review list', async ({ page }) => {
        await login(page, buyerEmail, buyerPassword);

        await page.goto('/app/invoices');

        await expect(page.getByRole('heading', { name: 'Invoices' })).toBeVisible();
        await expect(page.getByText('Track invoice submissions tied to purchase orders', { exact: false })).toBeVisible();
        await expect(page.getByLabel('Status')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Reset filters' })).toBeVisible();
    });
});

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');

    const loginForm = page.locator('form');
    await expect(loginForm).toBeVisible();

    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);

    await Promise.all([
        page.waitForURL(/\/app(\b|\/).*/i, { timeout: 30000 }),
        page.getByRole('button', { name: /sign in/i }).click(),
    ]);
}
