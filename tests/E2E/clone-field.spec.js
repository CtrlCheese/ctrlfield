// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: Clone field — prefixed field keys appear in admin, save and retrieve correctly.
 * Requires wp-env with ctrlfield-pro active and dev-test.php clone group registered.
 *
 * Acceptance criteria (Cycle B-6):
 * - Cloned fields with prefix appear in meta box
 * - Seamless clone: saved as flat keys (client_full_name, client_email, etc.)
 * - Group clone: saved as nested object (agency_contact.{full_name, email, phone})
 */

test.describe('Clone field', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');
        await page.locator('.ctrlfield-container').first().waitFor({ timeout: 10_000 });
    });

    test('seamless clone fields appear as individual inputs', async ({ page }) => {
        const container = page.locator('.ctrlfield-container').first();

        // Seamless clone inputs: client_full_name, client_email, client_phone
        await expect(container.locator('[x-model*="client_full_name"]')).toBeVisible();
        await expect(container.locator('[x-model*="client_email"]')).toBeVisible();
        await expect(container.locator('[x-model*="client_phone"]')).toBeVisible();
    });

    test('group clone appears as nested inputs under a group wrapper', async ({ page }) => {
        const container = page.locator('.ctrlfield-container').first();

        // Group clone: agency_contact group with sub-fields
        await expect(container.locator('[x-model*="agency_contact"]')).toBeVisible();
    });

    test('clone fields save and retrieve correctly', async ({ page }) => {
        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('Clone E2E Test ' + Date.now());

        const container = page.locator('.ctrlfield-container').first();

        // Fill seamless clone fields
        await container.locator('[x-model*="client_full_name"]').fill('Jane E2E');
        await container.locator('[x-model*="client_email"]').fill('jane@e2e.test');

        // Save
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');

        // Reload and verify
        await page.reload();
        const reloadedContainer = page.locator('.ctrlfield-container').first();
        await reloadedContainer.waitFor({ timeout: 10_000 });

        await expect(reloadedContainer.locator('[x-model*="client_full_name"]')).toHaveValue('Jane E2E');
        await expect(reloadedContainer.locator('[x-model*="client_email"]')).toHaveValue('jane@e2e.test');
    });

});
