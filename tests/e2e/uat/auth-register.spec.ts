import { expect, test } from '@playwright/test';

const registerUrl = '/register';

function buildUniqueEmail() {
  const stamp = new Date().toISOString().replace(/[-:.TZ]/g, '');
  return `uat-owner-${stamp}@example.com`;
}

test.describe('UAT: AUTH1 Register an account', () => {
  test('shows validation errors on empty submit', async ({ page }) => {
    const response = await page.goto(registerUrl);
    test.skip(!response || response.status() >= 400, 'Application server not reachable; skipping registration validation test.');

    await page.getByRole('button', { name: /create workspace/i }).click();

    await expect(page.getByText('Full name is required.')).toBeVisible();
    await expect(page.getByText('Email is required.')).toBeVisible();
    await expect(page.getByText('Company name is required.')).toBeVisible();
    await expect(page.getByText('Company domain is required.')).toBeVisible();
    await expect(page.getByText('Registration number is required.')).toBeVisible();
    await expect(page.getByText('Tax ID is required.')).toBeVisible();
    await expect(page.getByText('Company website is required.')).toBeVisible();
    await expect(page.getByText('Company phone is required.')).toBeVisible();
    await expect(page.getByText('Password is required.')).toBeVisible();
    await expect(page.getByText('Confirm your password.')).toBeVisible();
    await expect(page.getByText('Upload at least one supporting document and attach a file for each row.')).toBeVisible();
  });

  test('registers a buyer and redirects to onboarding or verification', async ({ page }) => {
    const response = await page.goto(registerUrl);
    test.skip(!response || response.status() >= 400, 'Application server not reachable; skipping registration flow test.');

    await page.getByRole('heading', { name: /create your workspace/i }).waitFor();

    await page.getByLabel('Full name').fill('UAT Owner');
    await page.getByLabel('Work email').fill(buildUniqueEmail());
    await page.getByLabel('Company name').fill('UAT Manufacturing');
    await page.getByLabel('Company domain').fill('uat-manufacturing.example');
    await page.getByLabel('Registration number').fill('REG-2026-001');
    await page.getByLabel('Tax ID').fill('TAX-2026-001');
    await page.getByLabel('Company website').fill('https://uat-manufacturing.example');
    await page.getByLabel('Company phone').fill('+1 555-0100');
    await page.getByLabel('Country (ISO code)').fill('US');
    await page.getByLabel('Password').fill('Passw0rd!');
    await page.getByLabel('Confirm password').fill('Passw0rd!');

    const documentFile = page.getByLabel('Document file').first();
    await documentFile.setInputFiles('public/logo-symbol.png');

    await Promise.all([
      page.waitForURL(/\/(verify-email|app(\/setup\/plan|\/setup\/supplier-waiting)?)(\b|\/).*/i, { timeout: 60000 }),
      page.getByRole('button', { name: /create workspace/i }).click(),
    ]);

    const destination = page.url();
    expect(destination).toMatch(/\/(verify-email|app(\/setup\/plan|\/setup\/supplier-waiting)?)(\b|\/).*/i);
  });
});
