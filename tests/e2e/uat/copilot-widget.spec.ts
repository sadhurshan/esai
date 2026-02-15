import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

function loadDotEnvForTests() {
  const envPath = path.resolve(process.cwd(), '.env');
  if (!fs.existsSync(envPath)) {
    return;
  }

  const content = fs.readFileSync(envPath, 'utf8');
  for (const rawLine of content.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (line === '' || line.startsWith('#')) {
      continue;
    }

    const separatorIndex = line.indexOf('=');
    if (separatorIndex <= 0) {
      continue;
    }

    const key = line.slice(0, separatorIndex).trim();
    let value = line.slice(separatorIndex + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

loadDotEnvForTests();

const buyerEmail =
  process.env.PLAYWRIGHT_BUYER_EMAIL ??
  process.env.UAT_BUYER_EMAIL ??
  'buyer.admin@example.com';
const buyerPassword =
  process.env.PLAYWRIGHT_BUYER_PASSWORD ??
  process.env.UAT_BUYER_PASSWORD ??
  'password';

const buyerCredsMissing = buyerEmail === '' || buyerPassword === '';

const COPILOT_FEATURE_FLAGS = [
  'ai_workflows_enabled',
  'approvals_enabled',
  'ai.copilot',
  'ai_copilot',
  'ai.enabled',
];

test.describe('UAT: Copilot widget', () => {
  test('buyer can open and close Copilot', async ({ page }) => {
    if (buyerCredsMissing) {
      throw new Error(
        'Set PLAYWRIGHT_BUYER_EMAIL and PLAYWRIGHT_BUYER_PASSWORD to run Copilot widget tests.',
      );
    }

    const copilotLogs: string[] = [];
    page.on('console', (message) => {
      const text = message.text();
      if (text.includes('[copilot] widget gated')) {
        copilotLogs.push(text);
      }
    });

    await bootstrapAuth(page, buyerEmail, buyerPassword);

    const storedAuth = await page.evaluate(() =>
      window.localStorage.getItem('esai.auth.state'),
    );

    if (!storedAuth) {
      throw new Error('Unable to load auth session for Copilot widget test.');
    }

    let authData = JSON.parse(storedAuth) as {
      featureFlags?: Record<string, boolean>;
      user?: { role?: string | null } | null;
      personas?: Array<{ key: string; type?: string | null; role?: string | null; is_default?: boolean }>; 
      activePersonaKey?: string | null;
    };

    let featureFlags = authData?.featureFlags ?? {};
    const copilotEnabled = COPILOT_FEATURE_FLAGS.some(
      (flag) => featureFlags?.[flag] === true,
    );

    if (!copilotEnabled) {
      throw new Error('Copilot feature flag not enabled for this account.');
    }

    let personas = authData?.personas ?? [];
    let activePersonaKey = authData?.activePersonaKey ?? null;
    let activePersona =
      personas.find((persona) => persona.key === activePersonaKey) ??
      personas.find((persona) => persona.is_default) ??
      null;

    if (activePersona?.type === 'supplier') {
      const buyerPersona = personas.find((persona) => persona.type === 'buyer');
      if (buyerPersona) {
        authData = { ...authData, activePersonaKey: buyerPersona.key };
        await page.evaluate((payload) => {
          window.localStorage.setItem('esai.auth.state', JSON.stringify(payload));
        }, authData);
        await page.reload();

        const updatedAuth = await page.evaluate(() =>
          window.localStorage.getItem('esai.auth.state'),
        );
        if (updatedAuth) {
          authData = JSON.parse(updatedAuth) as typeof authData;
          personas = authData?.personas ?? [];
          activePersonaKey = authData?.activePersonaKey ?? null;
          activePersona =
            personas.find((persona) => persona.key === activePersonaKey) ??
            personas.find((persona) => persona.is_default) ??
            null;
          featureFlags = authData?.featureFlags ?? {};
        }
      }
    }
    const personaType = String(activePersona?.type ?? '').toLowerCase();
    const personaRole = String(activePersona?.role ?? '').toLowerCase();
    const userRole = String(authData?.user?.role ?? '').toLowerCase();
    const resolvedRole = personaRole || userRole;

    if (personaType === 'supplier' || resolvedRole.startsWith('supplier_')) {
      throw new Error('Copilot is disabled for supplier personas.');
    }

    if (resolvedRole === 'platform_super') {
      throw new Error('Copilot is disabled for platform super personas.');
    }

    await page.waitForFunction(
      () => Boolean(document.getElementById('copilot-chat-widget-root')),
      { timeout: 30000 },
    );
    const widgetRootPresent = await page.evaluate(
      () => Boolean(document.getElementById('copilot-chat-widget-root')),
    );
    if (!widgetRootPresent) {
      const authSnapshot = await page.evaluate(() =>
        window.localStorage.getItem('esai.auth.state'),
      );
      const gateLog = copilotLogs.length > 0 ? ` Gate: ${copilotLogs[0]}` : '';
      throw new Error(
        `Copilot widget root missing.${gateLog} Auth: ${(authSnapshot ?? '').slice(0, 300)}`,
      );
    }

    const copilotButton = page.getByRole('button', { name: 'AI Copilot' });
    await expect(copilotButton).toBeVisible({ timeout: 30000 });
    await expect(copilotButton).toHaveAttribute('aria-expanded', 'false');

    await copilotButton.click();

    await expect(copilotButton).toHaveAttribute('aria-expanded', 'true');
    const dockHeading = page.getByRole('heading', { name: 'AI Copilot' });
    await expect(dockHeading).toBeVisible();

    const closeButton = page.getByRole('button', { name: 'Close Copilot' });
    await expect(closeButton).toBeVisible();
    await closeButton.click();

    await expect(copilotButton).toHaveAttribute('aria-expanded', 'false');
    await expect(dockHeading).toBeHidden();
  });
});

async function bootstrapAuth(page: import('@playwright/test').Page, email: string, password: string) {
  const response = await page.request.post('/api/auth/login', {
    data: { email, password, remember: true },
    headers: { Accept: 'application/json' },
  });

  if (!response.ok()) {
    const body = await response.text();
    throw new Error(`Login API failed. Status ${response.status()}. Body: ${body.slice(0, 200)}`);
  }

  const payload = (await response.json()) as { data?: Record<string, unknown> } | Record<string, unknown>;
  const data = 'data' in payload ? (payload.data ?? {}) : payload;

  const token = (data as { token?: string }).token;
  const user = (data as { user?: Record<string, unknown> }).user ?? null;
  const company = (data as { company?: Record<string, unknown> | null }).company ?? null;
  const rawFlags = (data as { feature_flags?: unknown }).feature_flags;
  const featureFlags = normalizeFeatureFlags(rawFlags);
  const plan = (data as { plan?: string | null }).plan ?? null;
  const requiresPlanSelection = Boolean((data as { requires_plan_selection?: boolean }).requires_plan_selection);
  const requiresEmailVerification = Boolean((data as { requires_email_verification?: boolean }).requires_email_verification);
  const needsSupplierApproval = Boolean((company as { supplier_status?: string | null })?.supplier_status === 'pending');
  const personas = Array.isArray((data as { personas?: unknown }).personas) ? (data as { personas?: unknown[] }).personas ?? [] : [];
  const activePersona = (data as { active_persona?: { key?: string } | null }).active_persona ?? null;

  if (!token || !user) {
    throw new Error('Login API response missing token or user.');
  }

  const activePersonaKey = resolveActivePersonaKey(personas, activePersona?.key ?? null);

  const storedAuthState = {
    token,
    user,
    company,
    featureFlags,
    plan,
    requiresPlanSelection,
    requiresEmailVerification,
    needsSupplierApproval,
    personas,
    activePersonaKey,
  };

  await page.addInitScript((state) => {
    window.localStorage.setItem('esai.auth.state', JSON.stringify(state));
  }, storedAuthState);

  await page.goto('/app', { waitUntil: 'domcontentloaded' });
  await page.waitForURL(/\/app(\b|\/).*/i, { timeout: 60000 });
}

function normalizeFeatureFlags(flags: unknown): Record<string, boolean> {
  if (!flags) {
    return {};
  }

  if (Array.isArray(flags)) {
    return flags.reduce<Record<string, boolean>>((acc, flag) => {
      if (typeof flag === 'string') {
        acc[flag] = true;
      }
      return acc;
    }, {});
  }

  if (typeof flags === 'object') {
    return Object.entries(flags as Record<string, unknown>).reduce<Record<string, boolean>>(
      (acc, [key, value]) => {
        if (typeof value === 'boolean') {
          acc[key] = value;
        }
        return acc;
      },
      {},
    );
  }

  return {};
}

function resolveActivePersonaKey(personas: unknown[], preferredKey: string | null): string | null {
  const typed = personas.filter(
    (persona): persona is { key: string; type?: string; is_default?: boolean } =>
      typeof persona === 'object' && persona !== null && 'key' in persona,
  );

  if (preferredKey) {
    const match = typed.find((persona) => persona.key === preferredKey);
    if (match) {
      return match.key;
    }
  }

  const defaultBuyer = typed.find((persona) => persona.type === 'buyer' && persona.is_default);
  if (defaultBuyer) {
    return defaultBuyer.key;
  }

  const anyBuyer = typed.find((persona) => persona.type === 'buyer');
  if (anyBuyer) {
    return anyBuyer.key;
  }

  return typed[0]?.key ?? null;
}
