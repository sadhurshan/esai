import { defineConfig, devices } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

function loadDotEnv() {
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

loadDotEnv();

function normalizeViteHotFile() {
    const hotPath = path.resolve(process.cwd(), 'public', 'hot');
    if (!fs.existsSync(hotPath)) {
        return;
    }

    const current = fs.readFileSync(hotPath, 'utf8').trim();
    const normalized = current.replace('http://[::1]:5173', 'http://127.0.0.1:5173');
    if (normalized !== current) {
        fs.writeFileSync(hotPath, `${normalized}\n`, 'utf8');
    }
}

normalizeViteHotFile();

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
process.env.VITE_API_BASE_URL = process.env.VITE_API_BASE_URL ?? baseURL;
const reuseExistingServer =
    process.env.PLAYWRIGHT_REUSE_SERVER === 'false'
        ? false
        : !process.env.CI;

export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 120000,
    expect: {
        timeout: 5000,
    },
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    reporter: [['list'], ['html', { outputFolder: 'storage/playwright-report' }]],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
    ],
    webServer: {
        command: process.env.PLAYWRIGHT_WEB_SERVER ?? 'npm run dev:e2e',
        url: baseURL,
        reuseExistingServer,
        timeout: 120000,
    },
});
