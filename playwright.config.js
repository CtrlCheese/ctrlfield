// @ts-check
const fs = require('fs');
const { defineConfig, devices } = require('@playwright/test');

// Pro specs live in pro/tests/E2E — absent from the public Free repo.
const hasPro = fs.existsSync('./pro/tests/E2E');

/**
 * CtrlField E2E test configuration.
 *
 * Tests run against a live wp-env environment.
 * Start it with:  npx @wordpress/env start
 * Then run E2E:   npx playwright test
 * Core only:      npx playwright test --project=chromium
 * Pro only:       npx playwright test --project=chromium-pro
 */
module.exports = defineConfig({
    testMatch:   '**/*.spec.js',
    fullyParallel: false,   // WP tests share state — run sequentially
    retries:     1,

    use: {
        baseURL:     'http://localhost:8888',
        trace:       'on-first-retry',
        screenshot:  'only-on-failure',
        video:       'retain-on-failure',
        // WP admin credentials
        storageState: 'tests/E2E/.auth/admin.json',
    },

    projects: [
        // Auth setup — runs first, saves admin session to disk
        {
            name: 'setup',
            testDir: './tests/E2E',
            testMatch: '**/*.setup.js',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'chromium',
            testDir: './tests/E2E',
            use: { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
        },
        ...(hasPro ? [{
            name: 'chromium-pro',
            testDir: './pro/tests/E2E',
            use: { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
        }] : []),
    ],

    // Automatically start wp-env before running tests if desired:
    // webServer: {
    //     command: 'npx @wordpress/env start',
    //     url:     'http://localhost:8888',
    //     reuseExistingServer: true,
    // },
});
