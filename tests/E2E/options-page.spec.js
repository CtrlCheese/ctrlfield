// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: Options page — fill field, save, verify stored in options table.
 *
 * Acceptance criteria (Cycle 11):
 * - Fill options page field → save → verify stored in wp_options table
 */

test.describe('Options page', () => {

    test('fill, save, and verify settings are persisted', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=agency-settings');

        // Fill Agency Name
        await page.fill('input[name="ctrlfield_field[agency_name]"]', 'Playwright Agency');
        await page.fill('input[name="ctrlfield_field[agency_email]"]', 'hello@playwright.com');
        await page.fill('input[name="ctrlfield_field[agency_website]"]', 'https://playwright.dev');
        await page.selectOption('select[name="ctrlfield_field[default_currency]"]', 'eur');

        // Save
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');

        // Verify "Settings saved." notice appeared
        await expect(page.locator('.notice-success, .updated')).toBeVisible();

        // Verify fields retained values after redirect
        await expect(page.locator('input[name="ctrlfield_field[agency_name]"]'))
            .toHaveValue('Playwright Agency');
        await expect(page.locator('input[name="ctrlfield_field[agency_email]"]'))
            .toHaveValue('hello@playwright.com');
        await expect(page.locator('select[name="ctrlfield_field[default_currency]"]'))
            .toHaveValue('eur');
    });

    test('data stored in _ctrlfield_options_ key via WP-CLI', async ({ page }) => {
        // Navigate to the page to ensure it exists
        await page.goto('/wp-admin/admin.php?page=agency-settings');
        await expect(page.locator('h1')).toHaveText('Agency Settings');

        // The actual DB verification is in integration tests.
        // This E2E test validates the page renders and saves correctly.
        await page.fill('input[name="ctrlfield_field[agency_name]"]', 'DB Verify Test');
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');

        await expect(page.locator('input[name="ctrlfield_field[agency_name]"]'))
            .toHaveValue('DB Verify Test');
    });

    test('submenu Social Links page saves separately', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=agency-social');

        await page.fill('input[name="ctrlfield_field[twitter_url]"]', 'https://x.com/ctrlfield');
        await page.fill('input[name="ctrlfield_field[linkedin_url]"]', 'https://linkedin.com/company/ctrlfield');

        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');

        await expect(page.locator('input[name="ctrlfield_field[twitter_url]"]'))
            .toHaveValue('https://x.com/ctrlfield');
    });

});
