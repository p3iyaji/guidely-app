import { defineConfig, devices } from '@playwright/test';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)));
const baseURL = 'http://127.0.0.1:8010';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: 'list',
    timeout: 60_000,
    expect: {
        timeout: 15_000,
    },
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        trace: 'on-first-retry',
    },
    webServer: {
        // Load .env.e2e (absolute SQLite path written by e2e-prepare) — do not reuse a local serve.
        command: 'php artisan serve --env=e2e --host=127.0.0.1 --port=8010',
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        cwd: root,
    },
});
