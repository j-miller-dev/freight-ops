import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8765);

/**
 * The app runs against its own database so the suite can reseed it freely.
 * Locally: create an `e2e` database and set E2E_DB_PORT / E2E_DB_USERNAME /
 * E2E_DB_PASSWORD to match the dev MySQL. In CI the defaults are used.
 */
export const appEnv = {
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_URL: `http://127.0.0.1:${port}`,
    DB_CONNECTION: 'mysql',
    DB_HOST: process.env.E2E_DB_HOST ?? '127.0.0.1',
    DB_PORT: process.env.E2E_DB_PORT ?? '3306',
    DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'e2e',
    DB_USERNAME: process.env.E2E_DB_USERNAME ?? 'root',
    DB_PASSWORD: process.env.E2E_DB_PASSWORD ?? '',
    SESSION_DRIVER: 'database',
    QUEUE_CONNECTION: 'sync',
    // The page loads in parallel with the XHRs it fires.
    PHP_CLI_SERVER_WORKERS: '4',
};

export default defineConfig({
    testDir: './e2e',
    // One shared database and one pool of seeded pallets: run in order.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : 'list',
    globalSetup: './e2e/global-setup.ts',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
        launchOptions: {
            // Locally, point at an installed Chromium instead of downloading one.
            executablePath: process.env.PW_CHROMIUM_PATH || undefined,
        },
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'chromium',
            use: {
                ...devices['Galaxy Tab S4 landscape'],
                storageState: 'e2e/.auth/state.json',
            },
            dependencies: ['setup'],
            testIgnore: /auth\.setup\.ts/,
        },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${port} --no-reload`,
        url: `http://127.0.0.1:${port}/session/ping`,
        env: appEnv,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
