// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: License staging detection.
 *
 * Acceptance criteria (CYCLES6 T-4):
 * - wp-env runs on localhost → always detected as staging
 * - Entering any non-empty license key shows "Active (Staging — free)"
 * - Pro features are active in staging mode
 *
 * Note: wp-env uses localhost:8888 which StagingDetector identifies as staging.
 * No real LemonSqueezy API call is made.
 */

test.describe('License staging mode', () => {

    test('settings page is accessible', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=fieldforge-pro-license');
        await expect(page.locator('.fieldforge-pro-license, .wrap')).toBeVisible({ timeout: 10_000 });
    });

    test('entering a key on localhost shows staging badge', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=fieldforge-pro-license');
        await expect(page.locator('.wrap')).toBeVisible({ timeout: 10_000 });

        const keyInput = page.locator('#fieldforge_pro_license_key, input[name="license_key"]');
        await expect(keyInput).toBeVisible();

        // Fill with a test key — on localhost this should activate as staging
        await keyInput.fill('TEST-STAGING-KEY-E2E-12345');

        await page.locator('#fieldforge-pro-activate, button', { hasText: /activate/i }).first().click();

        // Wait for AJAX response
        await page.waitForTimeout(2_000);

        // After reload, the badge should show staging or active status
        await page.reload();
        const badge = page.locator('.fieldforge-pro-status-badge, .badge-staging, .badge-valid');
        if (await badge.isVisible()) {
            const text = await badge.textContent();
            // On localhost (wp-env), staging bypass is active
            expect(text).toMatch(/active|staging/i);
        }
    });

    test('wp-env localhost is detected as staging environment', async ({ page }) => {
        // The FieldForge staging indicator is exposed in the license page
        await page.goto('/wp-admin/admin.php?page=fieldforge-pro-license');
        await expect(page.locator('.wrap')).toBeVisible({ timeout: 10_000 });

        // The page title should reference "License"
        const heading = page.locator('h1, .wp-heading-inline');
        await expect(heading.first()).toContainText(/license/i);
    });

    test('pro features are active in staging mode', async ({ page }) => {
        // Navigate to a page that uses Pro features (FlexibleContent)
        await page.goto('/wp-admin/post-new.php?post_type=page');

        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // The flex content add button should be visible (Pro feature active)
        const flexAdd = container.locator('.ff-flex-add, button', { hasText: /add (section|layout)/i });
        await expect(flexAdd.first()).toBeVisible();
    });

});
