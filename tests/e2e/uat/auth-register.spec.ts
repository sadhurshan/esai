import { expect, test } from '@playwright/test';

const registerUrl = '/register';

function buildUniqueEmail() {
  const stamp = new Date().toISOString().replace(/[-:.TZ]/g, '');
  return `uat-owner-${stamp}@example.com`;
}

function buildUniqueSuffix() {
  return new Date().toISOString().replace(/[-:.TZ]/g, '').slice(-10);
}

test.describe('UAT: AUTH1 Register an account', () => {
  test('shows validation errors on empty submit', async ({ page }) => {
    const response = await page.goto(registerUrl, { waitUntil: 'domcontentloaded' });
    test.skip(!response || response.status() >= 400, 'Application server not reachable; skipping registration validation test.');

    const submitButton = page.getByRole('button', { name: /create workspace/i });
    await submitButton.waitFor();
    await submitButton.click();

    await expect(page.getByText('Full name is required.')).toBeVisible();
    await expect(page.getByText('Email is required.')).toBeVisible();
    await expect(page.getByText('Company name is required.')).toBeVisible();
    await expect(page.getByText('Company domain is required.')).toBeVisible();
    await expect(page.getByText('Registration number is required.')).toBeVisible();
    await expect(page.getByText('Tax ID is required.')).toBeVisible();
    await expect(page.getByText('Company phone is required.')).toBeVisible();
    await expect(page.getByText('Enter a valid URL, e.g. https://example.com')).toBeVisible();
    await expect(page.getByText('Use at least 8 characters.')).toBeVisible();
    await expect(page.getByText('Confirm your password.')).toBeVisible();
  });

  test('registers a buyer and redirects to onboarding or verification', async ({ page }) => {
    const response = await page.goto(registerUrl, { waitUntil: 'domcontentloaded' });
    test.skip(!response || response.status() >= 400, 'Application server not reachable; skipping registration flow test.');

    await page.getByLabel('Full name').waitFor();

    await page.getByLabel('Full name').fill('UAT Owner');
    await page.getByLabel('Work email').fill(buildUniqueEmail());
    const suffix = buildUniqueSuffix();
    await page.getByLabel('Company name').fill(`UAT Manufacturing ${suffix}`);
    await page.getByLabel('Company domain').fill(`uat-${suffix}.example`);
    await page.getByLabel('Registration number').fill(`REG-${suffix}`);
    await page.getByLabel('Tax ID').fill(`TAX-${suffix}`);
    await page.getByLabel('Company website').fill('https://uat-manufacturing.example');
    await page.getByLabel('Company phone').fill('+1 555-0100');
    await page.getByLabel('Country (2-letter code)').fill('US');
    await page.getByLabel(/^Password$/i).fill('Passw0rd!');
    await page.getByLabel('Confirm password').fill('Passw0rd!');

    const documentFile = page.getByLabel('Document file').first();
    await documentFile.setInputFiles('public/logo-symbol.png');

    const registerResponse = page.waitForResponse(
      (response) => response.url().includes('/api/auth/register') && response.request().method() === 'POST',
    );

    await page.getByRole('button', { name: /create workspace/i }).click();

    const registerResult = await registerResponse;
    if (!registerResult.ok()) {
      const body = await registerResult.text();
      test.skip(
        body.includes('SMTP') || body.includes('Authentication credentials invalid'),
        'SMTP not configured for registration email delivery.',
      );
    }
    expect(registerResult.ok()).toBeTruthy();

    await page.waitForURL(/\/(verify-email|app(\/setup\/plan|\/setup\/supplier-waiting)?)(\b|\/).*/i, { timeout: 60000 });

    const destination = page.url();
    expect(destination).toMatch(/\/(verify-email|app(\/setup\/plan|\/setup\/supplier-waiting)?)(\b|\/).*/i);
  });
});
