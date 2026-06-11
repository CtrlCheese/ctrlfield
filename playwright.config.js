// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * FieldForge E2E test configuration.
 *
 * Tests run against a live wp-env environment.
 * Start it with:  npx @wordpress/env start
 * Then run E2E:   npx playwright test
 */
module.exports = defineConfig({
    testDir:     './tests/E2E',
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
            testMatch: '**/*.setup.js',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            dependencies: ['setup'],
        },
    ],

    // Automatically start wp-env before running tests if desired:
    // webServer: {
    //     command: 'npx @wordpress/env start',
    //     url:     'http://localhost:8888',
    //     reuseExistingServer: true,
    // },
});
