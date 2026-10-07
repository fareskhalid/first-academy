import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    timeout: 45000,
    expect: { timeout: 10000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: { baseURL: process.env.E2E_BASE_URL || 'http://browser-app', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    projects: [
        { name: 'android-chrome', use: { ...devices['Pixel 7'], browserName: 'chromium' } },
        { name: 'iphone-webkit', use: { ...devices['iPhone 13'], browserName: 'webkit' } },
        { name: 'desktop-chrome', use: { ...devices['Desktop Chrome'] } },
    ],
});
